<?php

return [
    'git'    => [
        'username'            => (string) env('GIT_USERNAME', 'muhammadumar.sotvoldiev'),
        'email'               => (string) env('GIT_EMAIL', 'muhammadumar.sotvoldiev@danads.se'),
        'mr_reviewer_name'    => (string) env('GIT_MR_REVIEWER_NAME', 'bohdan.bondar'),
        'path_to_root_folder' => (string) env('GIT_PATH_TO_ROOT_FOLDER'),
    ],
    'gitlab' => [
        'personal_access_token' => (string) env('PERSONAL_TOKEN'),
        'api_url'               => (string) env('GITLAB_API_URL'),
    ],
    'jira'   => [
        'email'                 => (string) env('JIRA_EMAIL'),
        'personal_access_token' => (string) env('JIRA_TOKEN'),
        'api_url'               => (string) env('JIRA_API_URL'),
    ],
];
