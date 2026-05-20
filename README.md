# Auto Git Cherry Picker with WMT (Jira) and VCS (GitLab) integrations

Language agnostic package for automated Git cherry-picking with WMT (Work Management Tool - Jira) and VCS (Version Control System - GitLab) integrations for team work. Can be usefull to backport changes to branches, clone fixes or features to separate projects. This package streamlines the process of cherry-picking commits across branches, automatically creating merge requests with proper metadata from WMT tickets (Jira).

[Demo video and presentation link](https://docs.google.com/presentation/d/1Ml6Oe2MLj1rt9jNuCKSDl3zp5pIaQIqUMb8pnTqHdVM/edit?usp=sharing)

## How it works (diagram)

![Cherry Picker Diagram](/assets/diagram.png)

## Features

- 🍒 Automated git cherry-pick workflow
- 🔗 WMT (Work Management Tool) - fetches ticket details automatically (Jira)
- 🦊 VCS (Version Control System) - creates merge requests with assignees and reviewers (GitLab)
- 💻 Interactive CLI mode with beautiful prompts
- 📦 Language-agnostic - works as a standalone package with any programming language
- ⚙️ Configurable via .env file

## Requirements

- PHP, composer

## Installation

Please read [QUICKSTART.md](QUICKSTART.md)

## What Happens During Execution?

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
