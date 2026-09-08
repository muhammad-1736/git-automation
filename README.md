# Auto Git Flow with WMT (Jira) and VCS (GitLab) integrations

Language agnostic package for automated Git Flow with WMT (Work Management Tool - Jira) and VCS (Version Control System - GitLab) integrations for team work. Can be usefull to backport changes to branches, clone fixes or features to separate projects. This package streamlines the process of cherry-picking commits across branches, automatically creating merge requests with proper metadata from WMT tickets (Jira).

[Demo video and presentation link](https://docs.google.com/presentation/d/1Ml6Oe2MLj1rt9jNuCKSDl3zp5pIaQIqUMb8pnTqHdVM/edit?usp=sharing)

## How it works (diagram)

![Cherry Picker Diagram](/assets/diagram.png)

## Features

-  Automated git cherry-pick workflow
-  WMT (Work Management Tool) - fetches ticket details automatically (Jira)
-  VCS (Version Control System) - creates merge requests with assignees and reviewers (GitLab)
-  Interactive CLI mode with beautiful prompts
-  Language-agnostic - works as a standalone package with any programming language
- ⚙️onfigurable via .env file

## Requirements

- PHP, composer

## Installation

Please read [QUICKSTART.md](QUICKSTART.md)

## Available Workflows

The tool offers an interactive CLI menu to execute any of the following workflows:

### 1. Local Codebase Preparation (`prepare_local_codebase`)
- **Why we need it:** Prepares and synchronizes your local platform git repository and core repository (`vendor/sigma/core`) to the fresh target branches from remote VCS matching the ticket's `fixVersion`. 
It also compares local Dockerfile (`phpfpm.Dockerfile`) base images with GitLab VCS to warn if **re-dockerization** is required.
- **How it works:**
  1. Prompts for platform.
  2. Prompts to input either a **fix version** directly or a **Jira ticket**.
  3. Resolves the target onboarding branch (`release/x.y.0` or `hotfix/x.y.z`) and inspects `composer.json` on GitLab to resolve the matching core branch.
  4. Compares local Dockerfile against VCS and warns if they differ to redockerize your local codebase.
  5. In both platform and core repositories: stashes local changes if any, fetches origin, checks out the target branch, pulls the latest changes, and restores stashed changes.

### 2. Commit Automation (`commit_automation`)
- **Why we need it:** Automates the repetitive steps of branching, committing with ticket metadata, pushing, and creating a GitLab Merge Request with designated reviewers directly from the CLI.
- **How it works:**
  1. Prompts for platform, repository type (onboarding platform or core side (vendor/sigma/core)), and Jira ticket.
  2. Stashes local changes, pulls the latest target branch, and checks out `feature/{ticket}` or `bugfix/{ticket}`.
  3. Stages modified files (excluding paths defined in `excluded_from_commit` config).
  4. Commits with Jira title/description, pushes to remote GitLab, and creates a Merge Request.

### 3. Clean Old Branches (`clean_old_branches`)
- **When to call it:** Call this when an MR was merged and branches were deleted from GitLab remote, and you need to clean up stale local branches or recreate them from scratch.
- **How it works:**
  1. Prompts for platform and ticket number.
  2. Checks for and forcefully deletes local `feature/{ticket}` and `bugfix/{ticket}` branches from both the platform repository and the core repository (`vendor/sigma/core`).

### 4. Update Core Dependency in Composer (`update_core_dependency`)
- **Why we need it:** Directly update `sigma/core` dependency branch and commit hash in `composer.json` and `composer.lock` without running a slow or breaking composer update.
- **How it works:**
  1. Prompts for platform using `select_platform()`.
  2. Prompts for branch name (e.g. `platform/7.4.3/drl`).
  3. Prompts for commit hash (e.g. `3bafb744b83a9144cab895ec8fc4d80bb4a1aa56`).
  4. Updates `require."sigma/core"` in `composer.json` to `"dev-{branch}"`.
  5. Updates package `"sigma/core"` in `composer.lock`:
     - `version` to `"dev-{branch}"`
     - `source.reference` to the provided commit hash
     - `time` to the current UTC timestamp (`Y-m-d\TH:i:s+00:00`).

### 5. Cherry Pick (`clone`)
Automates backporting commits from one branch to another or across projects.

1. **Preparation**
    - (Optional - turned ON by default), Configures git user-name/email, remote origin
    - Stashes current changes
2. **WMT (Jira) operations**
   - It gets using Jira API necessary data from Issue data: fix_version (e.g. 2.0.0, 2.0.5) ; feature or bug (from IssueType) ; summary (aka title)

2. **VCS (Gitlab) Branch Operations**
    - GitLab Project is defined by getting projectId from Gitlab by groupName and projectName. For example if Gitlab group is `core` and projectName is `platform` it will be: `core/platform`. You can see group and projectName from URL of your current Gitlab project: `https://gitlab.your.domain/core/platform`
    - Pulls latest from target branch  (by template: `release/fix_version` or `hotfix/fix_version` automatically according to fix_version) to local git
    - Creates new branch for cherry-pick from target branch (by template: `feature/ticket-number` or `bugfix/ticket-number` according to data from WMT )

3. **Cherry-Pick**
    - Applies commits with `-x` flag (preserves original commit reference)
    - Detects conflicts automatically

4. **Conflict Resolution** (if needed)
    - Tool pauses and shows conflict message
    - You resolve conflicts in your editor, SAVE FILES BUT DON'T ADD TO STAGE OR COMMIT THEM !!!!
    - Press Enter to continue
    - Tool stages changes, commits and continues

5. **Finalization**
    - Pushes new branch to remote
    - Creates merge request in GitLab
    - Assigns reviewer and asignee automatically
    - Returns to original branch in your local computer's git
    - Cleans up local cherry-pick branch
    - Restores stashed changes

## Architecture

The package uses a clean architecture with dependency injection:

- **Container** - PHP DI container managing all services
- **ConfigLoader** - Loads configuration from .env and config files
- **CherryPicker** - Main orchestrator for the cherry-pick workflow
- **GitClient** - Git operations wrapper
- **VCS Provider (GitLabClient)** - GitLab API integration (implements VCSProviderContract)
- **WMT Provider (JiraClient)** - Jira API integration (implements WMTProviderContract)
- **ShellRunner** - Executes shell commands using Symfony Process

### Main Dependencies

- `guzzlehttp/guzzle` - HTTP client for API requests
- `symfony/process` - Process execution
- `laravel/prompts` - Beautiful CLI prompts
- `vlucas/phpdotenv` - Environment variable loading
- `php-di/php-di` - Dependency injection container

### Testing Pest PHP    

Run tests in CLI (need to install dev dependencies with `composer install --dev`):
```bash
./vendor/bin/pest
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email muhammadumarsotvoldiev@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## PHP Package Boilerplate

This package was generated using the [PHP Package Boilerplate](https://laravelpackageboilerplate.com) by [Beyond Code](http://beyondco.de/).
