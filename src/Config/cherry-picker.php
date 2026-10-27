<?php

return [
    'git'    => [
        'username'            => (string) env('GIT_USERNAME', 'YOU'),
        'email'               => (string) env('GIT_EMAIL', 'YOU@test.t'),
        'mr_reviewer_name'    => (string) env('GIT_MR_REVIEWER_NAME', 'lead'),
        'path_to_root_folder' => (string) env('GIT_PATH_TO_ROOT_FOLDER'),
        'excluded_from_commit' => explode(',', (string) env('GIT_EXCLUDED_FROM_COMMIT', '.codegraph')),
    ],
    'gitlab' => [
        'personal_access_token' => (string) env('PERSONAL_TOKEN'),
        'api_url'               => (string) env('GITLAB_API_URL'),
        'mr_assignee_username'  => (string) env('GITLAB_MR_ASSIGNEE_USERNAME', 'YOU'),
        'review_mention'        => (string) env('REVIEW_MENTION', '@LEAD'),
        'merged_mr_days'        => (int) env('GITLAB_MERGED_MR_DAYS', 7),
    ],
    'jira'   => [
        'email'                 => (string) env('JIRA_EMAIL'),
        'personal_access_token' => (string) env('JIRA_TOKEN'),
        'api_url'               => (string) env('JIRA_API_URL'),
        'testers'               => explode(',', (string) env('JIRA_TESTERS', 'TESTERNAME1,TESTERNAME2')),
    ],
    'tempo'  => [
        'api_url'               => (string) env('TEMPO_API_URL', 'https://api.tempo.io/4'),
        'token'                 => (string) env('TEMPO_TOKEN'),
        // Required Tempo work attributes, valid values: GET {api_url}/work-attributes
        'activity'              => (string) env('TEMPO_ACTIVITY', 'Development'),
        'time_type'             => (string) env('TEMPO_TIME_TYPE', 'Normaltime'),
    ],
];
