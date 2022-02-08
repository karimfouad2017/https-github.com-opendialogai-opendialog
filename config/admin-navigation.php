<?php

return [
    'items' =>
        [
            [
                [
                    'title' => 'Designer',
                    'url' => '/admin/conversation-builder/scenario/:scenario',
                    'icon' => 'filter-descending',
                    'section' => 'conversation-builder'
                ],
                [
                    'title' => 'Message Editor',
                    'url' => '/admin/conversation-builder/scenario/:scenario/intents',
                    'icon' => 'edit-bubble',
                    'section' => 'message-editor'
                ],
                [
                    'title' => 'Interpreters Setup',
                    'url' => '/admin/interpreters',
                    'icon' => 'pattern',
                    'section' => 'interpreters'
                ],
                [
                    'title' => 'Actions Setup',
                    'url' => '/admin/actions',
                    'icon' => 'refresh',
                    'section' => 'actions'
                ],
                [
                    'title' => 'Interface settings',
                    'url' => '/admin/interface-settings',
                    'icon' => 'settings-sliders',
                    'section' => 'interface-settings'
                ],
            ],
            [
                [
                    'title' => 'Preview',
                    'url' => '/admin/demo',
                    'icon' => 'speech',
                    'section' => 'demo'
                ],
                [
                    'title' => 'Publish',
                    'url' => '/admin/publish',
                    'icon' => 'forward',
                    'section' => 'publish'
                ]
            ]
        ],
    'help' => [
        [
            'title' => 'Help and Support',
            'url' => 'https://opendialog.ai/support/',
            'icon' => 'info'
        ]
    ],
    'workspace' => [
        'enabled' => env('WORKSPACE_NAVIGATION', false)
    ]
];
