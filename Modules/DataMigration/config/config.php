<?php

return [
    'legacy_connection' => env('DATA_MIGRATION_LEGACY_CONNECTION', 'legacy'),
    'chunk_size' => (int) env('DATA_MIGRATION_CHUNK_SIZE', 200),
    'source_tables' => [
        'users', 'members', 'memberships', 'member_relations',
        'member_dependents', 'member_enums', 'member_details', 'member_units',
    ],
];
