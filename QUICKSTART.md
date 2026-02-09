# Cherry Picker - Quick Start Guide

## Installation & Setup

### 0. Clone this Repo

```bash
git clone LINK_TO_REPO
cd cherry_picker
```

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your credentials:

```env
# Git Configuration
GIT_USERNAME=your.username
GIT_EMAIL=your.email@company.com
MR_REVIEWER_NAME=reviewer.username

# GitLab Configuration (Get token from GitLab > Preferences > Access Tokens)
PERSONAL_TOKEN=glpat-xxxxxxxxxxxxxxxxxxxx
GITLAB_API_URL=https://gitlab.da.local/api/v4

# Jira Configuration (Get token from Atlassian Account Settings > Security > API tokens)
JIRA_EMAIL=your.email@company.com
JIRA_TOKEN=ATATTxxxxxxxxxxxxxxxxxxxxxx
JIRA_API_URL=https://your-company.atlassian.net/rest/api/3
```

### 3. Test the Installation

```bash
./bin/cherry-picker --help
```

You should see the help message with all available options.

## Usage Examples

```bash
./bin/cherry-picker
```

## Troubleshooting

### "Composer autoloader not found"
```bash
composer install
```

### "Failed to connect to GitLab/Jira"
- Check your API URLs in `.env`
- Verify your access tokens are valid
- Ensure you have network access to the services

### "Permission denied" when running bin/cherry-picker
```bash
chmod +x bin/cherry-picker
```

### Conflicts During Cherry-Pick
1. The tool will pause and show: "Please resolve the conflicts manually..."
2. Open the conflicted files in your editor
3. Resolve the conflicts
4. Save the files
5. Return to terminal and press Enter
6. The tool continues automatically

## Directory Structure

```
cherry-picker/
├── bin/
│   └── cherry-picker          # Executable CLI script
├── src/
│   ├── CherryPicker.php       # Main orchestrator
│   ├── Container.php          # Dependency injection container
│   ├── Config/
│   │   └── ConfigLoader.php   # Configuration management
|   |     └── cherry-picker.php  # Default configuration
│   ├── Clients/
│   │   ├── JiraClient.php     # Jira API client
|   |   |      ...........................
│   ├── Contracts/
│   ├── DataTransferObjects/
├── env.example                 # Example configuration
└── composer.json               # Package definition
```

## Next Steps

Read the full [README.md](README.md) for more details

## Support

For issues, please check:
- [README.md](README.md) - Full documentation
- [CONTRIBUTING.md](CONTRIBUTING.md) - Contribution guidelines
