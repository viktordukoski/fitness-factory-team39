"""Run the real startup script with namespaced PDO/sleep stubs, not MySQL/Docker.

Only a namespace and fixture definitions are inserted after strict_types; the
script's connection/retry/error-handling logic is otherwise unchanged. Fake
connections and sleeps record events so all branches run without network waits.
Usage: python tests/wait_for_db.py --php /path/to/php --output evidence.json
"""

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "docker/wait-for-db.php"
FAKE_ENV = {
    "DB_NAME": "fixture_database",
    "DB_USER": "fixture_private_user",
    "DB_PASSWORD": "fixture_private_password",
    "APP_KEY": "fixture_private_application_key",
}
RAW_EXCEPTION = "fixture raw exception must never be logged"

STUBS = r'''
namespace WaitForDbFixture;

function recordEvent(string $event, array $details = []): void {
    \file_put_contents(\getenv('WAIT_DB_TRACE'), \json_encode(
        ['event' => $event] + $details
    ) . "\n", FILE_APPEND);
}

class PDOException extends \Exception {
    public array $errorInfo;
    public function __construct(int $driverCode) {
        parent::__construct('fixture raw exception must never be logged: '
            . \getenv('DB_PASSWORD') . ' ' . \getenv('APP_KEY'));
        $this->errorInfo = ['HY000', $driverCode, $this->getMessage()];
    }
}

class PDO {
    public const ATTR_ERRMODE = 3;
    public const ERRMODE_EXCEPTION = 2;
    public const ATTR_TIMEOUT = 2;
    private static int $attempt = 0;
    private ?int $queryError = null;

    public function __construct(string $dsn, string $user, string $password, array $options) {
        $expectedDsn = \sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            \getenv('DB_HOST') ?: 'db', \getenv('DB_PORT') ?: '3306', \getenv('DB_NAME'));
        if ($dsn !== $expectedDsn || $user !== \getenv('DB_USER')
            || $password !== \getenv('DB_PASSWORD')
            || $options !== [self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION, self::ATTR_TIMEOUT => 2]) {
            throw new \RuntimeException('Connection configuration mismatch');
        }
        $scenario = \json_decode(\getenv('WAIT_DB_SCENARIO'), true, 512, JSON_THROW_ON_ERROR);
        $outcome = $scenario[\min(self::$attempt, \count($scenario) - 1)];
        self::$attempt++;
        recordEvent('connect', ['attempt' => self::$attempt]);
        if (\is_array($outcome)) {
            $this->queryError = $outcome['queryError'];
        } elseif ($outcome !== null) {
            throw new PDOException($outcome);
        }
    }

    public function query(string $query): bool {
        if ($query !== 'SELECT 1') {
            throw new \RuntimeException('Unexpected readiness query');
        }
        recordEvent('query');
        if ($this->queryError !== null) {
            throw new PDOException($this->queryError);
        }
        return true;
    }
}

function sleep(int $seconds): int {
    recordEvent('sleep', ['seconds' => $seconds]);
    return 0;
}
'''


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    portable_php = ROOT.parent / ".runtime/php/php.exe"
    parser.add_argument("--php", default=shutil.which("php") or str(portable_php))
    parser.add_argument("--output", type=Path)
    args = parser.parse_args()
    source_bytes = SOURCE.read_bytes()
    source = source_bytes.decode("utf-8")
    fixture, replacements = re.subn(
        r"(declare\(strict_types=1\);)", lambda match: match[1] + STUBS, source, count=1
    )
    if replacements != 1:
        raise SystemExit("Could not insert isolated fixture namespace after strict_types.")
    checks = []

    with tempfile.TemporaryDirectory(prefix="fitness-wait-db-") as temporary:
        directory = Path(temporary)
        script = directory / "fixture.php"
        script.write_text(fixture, encoding="utf-8", newline="\n")

        def run_case(name, outcomes, expected_exit, connections, sleeps, queries,
                     error_codes=(), missing=None, custom_host=False, fatal=False, exhausted=False):
            trace = directory / "trace.jsonl"
            if trace.exists():
                trace.unlink()
            environment = os.environ.copy()
            for key in (*FAKE_ENV, "DB_HOST", "DB_PORT"):
                environment.pop(key, None)
            environment.update(FAKE_ENV)
            if missing:
                environment[missing] = " \t "
            if custom_host:
                environment.update(DB_HOST="fixture-db", DB_PORT="13306")
            environment.update(WAIT_DB_SCENARIO=json.dumps(outcomes), WAIT_DB_TRACE=str(trace))
            result = subprocess.run(
                [args.php, str(script)], env=environment, capture_output=True,
                text=True, timeout=10, check=False,
            )
            events = [json.loads(line) for line in trace.read_text().splitlines()] if trace.exists() else []
            output = result.stdout + result.stderr
            failures = []
            if result.returncode != expected_exit:
                failures.append("unexpected exit status")
            for event, count in (("connect", connections), ("sleep", sleeps), ("query", queries)):
                if sum(item["event"] == event for item in events) != count:
                    failures.append("unexpected " + event + " count")
            if any(item["seconds"] != 2 for item in events if item["event"] == "sleep"):
                failures.append("unexpected retry pause")
            observed_codes = re.findall(r"Waiting for database \(MySQL error (\d+)\)\.", result.stderr)
            if observed_codes != [str(code) for code in error_codes]:
                failures.append("error-code logging/deduplication mismatch")
            if result.stdout != ("Database ready.\n" if expected_exit == 0 else ""):
                failures.append("incorrect success output")
            if missing and result.stderr != f"Required environment value missing: {missing}\n":
                failures.append("missing environment diagnostic mismatch")
            if fatal and not all(term in result.stderr.lower() for term in ("db_user", "db_password", "db_name", "permissions")):
                failures.append("fatal diagnostic omits configuration or permissions")
            if fatal and re.search(r"\b(reset|delete|remove)\b", result.stderr, re.I):
                failures.append("fatal diagnostic recommends destructive action")
            if exhausted and ("60 attempts" not in result.stderr or "120 seconds" in result.stderr):
                failures.append("exhaustion message incorrectly promises elapsed time")
            if any(secret in output for secret in (*FAKE_ENV.values(), RAW_EXCEPTION)):
                failures.append("private fixture value or raw exception leaked")
            checks.append({"test": name, "status": "FAIL" if failures else "PASS", **({"failures": failures} if failures else {})})

        for required in FAKE_ENV:
            run_case("Missing/blank " + required + " stops before connection", [None], 1, 0, 0, 0, missing=required)
        run_case("Immediate readiness and default host/port", [None], 0, 1, 0, 1)
        run_case("Explicit host/port used", [None], 0, 1, 0, 1, custom_host=True)
        run_case("Transient connection error recovers", [2002, None], 0, 2, 1, 1, [2002])
        run_case("Readiness query error recovers", [{"queryError": 2013}, None], 0, 2, 1, 2, [2013])
        run_case("Error code zero is logged on first failure", [0, 0, None], 0, 3, 2, 1, [0])
        run_case("Repeated errors deduplicated; changes reported", [2002, 2002, 2006, 2006, 2002, None], 0, 6, 5, 1, [2002, 2006, 2002])
        for code in (1044, 1045, 1049):
            run_case(f"MySQL {code} stops immediately with safe diagnostic", [code, None], 1, 1, 0, 0, [code], fatal=True)
        run_case("Exhaustion stops after 60 connections and 59 pauses", [2002], 1, 60, 59, 0, [2002], exhausted=True)

    report = {
        "scope": "Startup control-flow regression using the actual PHP source with namespaced PDO, PDOException and sleep stubs; no real MySQL connection, Docker build or hosted deployment is tested.",
        "source": "docker/wait-for-db.php",
        "source_sha256": hashlib.sha256(source_bytes).hexdigest(),
        "checks": checks,
        "passed": sum(check["status"] == "PASS" for check in checks),
        "total": len(checks),
    }
    encoded = json.dumps(report, indent=2) + "\n"
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(encoded, encoding="utf-8", newline="\n")
    print(encoded, end="")
    return int(report["passed"] != report["total"])


if __name__ == "__main__":
    raise SystemExit(main())
