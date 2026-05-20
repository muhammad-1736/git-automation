# Cherry Picker

Automated Git Cherry Picker with Jira (WMT) and GitLab (VCS) integrations. This tool streamlines the process of backporting changes or cloning features/fixes to different branches or projects by automating the git workflow and integrating with work management and version control systems.

## Project Overview

- **Purpose:** Automate git cherry-picking, fetch issue details from Jira, and create Merge Requests in GitLab.
- **Main Technologies:** PHP 8.4+, Composer, PHP-DI, Guzzle, Symfony Process, Laravel Prompts.
- **Architecture:** Clean architecture with dependency injection and contract-based integrations.
    - `src/CherryPicker.php`: Orchestrates the entire workflow.
    - `src/Container.php`: Manages service instantiation and injection.
    - `src/Clients/`: Contains Git, GitLab, and Jira clients.
    - `src/Contracts/`: Defines interfaces for VCS and WMT providers.

## Building and Running

### Prerequisites
- PHP 8.4 or higher
- Composer

### Key Commands

- **Install Dependencies:**
  ```bash
  composer install
  ```

- **Run Tests:**
  ```bash
  ./vendor/bin/pest
  ```

- **Execute Tool:**
  ```bash
  ./bin/cherry-picker
  ```

- **Setup Configuration:**
  1. Copy the example environment file: `cp .env.example .env`
  2. Configure your Jira and GitLab credentials in the `.env` file.

## Development Conventions

- **PSR-4 Autoloading:** Namespace `Mu\CherryPicker` is mapped to the `src/` directory.
- **Dependency Injection:** Services are managed via `src/Container.php`. Prefer using the container or DI in constructors rather than manual instantiation.
- **Contracts:** New VCS or WMT providers should implement `VCSProviderContract` or `WMTProviderContract` respectively.
- **Testing:** Use Pest PHP for unit testing. Tests are located in the `tests/` directory.
- **CLI Interaction:** Use `laravel/prompts` for consistent and interactive CLI experiences.
- **Error Handling:** Shell commands are executed via `ShellRunner` (wrapping Symfony Process), which should handle output and errors appropriately.

## Environment Variables

The tool requires several environment variables defined in `.env`:
- `GIT_USERNAME`, `GIT_EMAIL`: For git operations.
- `PERSONAL_TOKEN`, `GITLAB_API_URL`: For GitLab API access.
- `JIRA_EMAIL`, `JIRA_TOKEN`, `JIRA_API_URL`: For Jira API access.
