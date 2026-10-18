# Dependencies and asset rights

The production application uses no JavaScript framework, frontend package dependency, external font, paid API, mail provider or payment gateway. CSS, vanilla JavaScript, SVG icons/artwork and application PHP are included as project source.

| Component | Use | Upstream license/reference |
|---|---|---|
| PHP 8.3.35, standard extensions | Application runtime | PHP License 3.01; [php.net/license](https://www.php.net/license/) |
| Apache HTTP Server | Web server in official PHP image | Apache License 2.0; [apache.org/licenses](https://www.apache.org/licenses/) |
| MariaDB 11.4.13 | Persistent relational database | GPL v2; [MariaDB licensing](https://mariadb.com/kb/en/licensing-faq/) |
| Operating-system packages / ICU / GD | Runtime image and image/locale support | Individual upstream licenses are distributed by the Debian/official image packages; not vendored into this source tree |
| Playwright | Local browser verification only | Apache License 2.0; [Playwright repository](https://github.com/microsoft/playwright) |
| PyYAML | Local Compose/stack syntax checks only | MIT; [PyYAML repository](https://github.com/yaml/pyyaml) |

The official container images retain their upstream software notices. Source delivery does not transfer Fitness Factory's trademarks or business identity. The FF-inspired mark is explicitly a proposed project graphic. Original representative SVG scenes and fictional avatars require no third-party photograph license; supplied promotional photographs are not reused. No blanket license to real business marks or screenshots is asserted.

The portable runtimes used for local verification are kept outside the repository and are not included in the source archive. Image-tag checks are documented in DEPLOYMENT.md; exact Docker runtime validation remains outstanding.
