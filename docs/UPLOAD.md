# Upload the reviewed project to GitHub

The user authorized a source push to the private repository [viktordukoski/fitness-factory-team39](https://github.com/viktordukoski/fitness-factory-team39), branch `main`, on 18 October 2026. Deployment remains paused. The instructions below also apply to future manual updates; use the latest reviewed `fitness-factory-source.zip`.

GitHub Desktop is recommended for this project because it contains more than 100 files. It preserves folders and makes it easier to check what will be uploaded.

1. Sign in to GitHub Desktop with the account that can access the repository. Choose **File → Clone repository**, select `viktordukoski/fitness-factory-team39` (or enter its URL), and choose a new local folder.
2. Extract the source ZIP into a separate folder. Open the extracted folder until you can see `Dockerfile`, `docker-compose.yml` and `stack.yml` together.
3. Copy **the contents of that folder** into the cloned repository folder. Do not place the enclosing `fitness-factory` folder inside the repository: the three deployment files must be directly at the repository root.
4. Include the supplied dotfiles: `.dockerignore`, `.gitignore`, `.gitattributes`, `.env.example`, `public/.htaccess` and `public/uploads/.gitkeep`. Show hidden files in your file manager if needed. Keep the `.git` folder created by GitHub Desktop in place; do not copy another `.git` folder over it.
5. In GitHub Desktop, review the **Changes** list. Do not include a real `.env`, `.runtime`, `.stack-data`, backups, `.git`, or test-uploaded images. The secret-free `.env.example` configuration template and `public/uploads/.gitkeep` should be included. The supplied ignore rules protect local runtime files, but still review the list before committing.
6. Confirm the branch is **main**, enter a summary such as “Add reviewed Fitness Factory project”, and choose **Commit to main**. Then choose **Push origin** (or **Publish branch** if shown).
7. Open the repository on GitHub. Its first page should show `Dockerfile`, `docker-compose.yml`, `stack.yml`, `README.md` and folders such as `app`, `public`, `database`, `bin` and `docker`. Open `public` and confirm `.htaccess` is present. Keep the repository private.

Do not upload the ZIP as a single file: RepoRun needs the extracted project files. A GitHub repository link is a source-code link, not the website link. This PHP/MariaDB application needs its prepared two-service deployment; GitHub Pages or RepoRun's static-site template will not run its accounts, bookings or database.

After uploading, tell the assistant **“The reviewed files are on main; resume RepoRun setup.”** Remaining work includes giving RepoRun read access to the private repository, entering private environment values and the initial hosted administrator password, running Validate, deploying, and checking the shared link. Uploading source alone does not perform those steps.

The project has local test evidence, but those checks do not verify Docker execution or RepoRun hosting. See [TESTING.md](TESTING.md) and [DEPLOYMENT.md](DEPLOYMENT.md) for the recorded results and remaining checks.
