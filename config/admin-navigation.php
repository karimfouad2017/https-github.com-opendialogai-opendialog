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
                    'url' => '/admin/webchat-setting',
                    'icon' => 'settings-sliders',
                    'section' => 'webchat-setting'
                ],
            ],
            [
                [
                    'title' => 'Preview',
                    'url' => '/admin/demo',
                    'icon' => 'speech',
                    'section' => 'demo'
                ]
            ]
        ],
    'help' => [
        [
            'title' => 'Watch Tutorials',
            'url' => 'https://docs.opendialog.ai/getting-started-1/getting-started-with-opendialog',
            'icon' => 'tutorials'
        ],
        [
            'title' => 'Documentation',
            'url' => 'https://docs.opendialog.ai',
            'icon' => 'document'
        ],
        [
            'title' => 'Contact Us',
            'url' => 'https://opendialog.ai/support',
            'icon' => 'email'
        ]
    ]
];
