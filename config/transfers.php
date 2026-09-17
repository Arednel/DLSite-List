<?php

return [
    // Default ZIP part size in MiB.
    'default_part_mib' => 256,

    // Available ZIP part sizes in MiB.
    'part_size_presets_mib' => [8, 16, 32, 64, 128, 256, 512, 1024, 2048, 4096, 8192],

    // Maximum transfer job runtime in seconds.
    'job_timeout' => 3500,

    // Transfer lock lifetime in seconds.
    'lock_seconds' => 3600,

    // Target fragment size in bytes.
    'fragment_bytes' => 8388608, // 8 * 1024 * 1024

    // Maximum JSON document size in bytes.
    'max_json_bytes' => 16777216, // 16 * 1024 * 1024

    // Maximum manifest size in bytes.
    'max_manifest_bytes' => 8388608, // 8 * 1024 * 1024

    // Maximum archive or inventory entries.
    'max_entries' => 100000,

    // Maximum records per JSON fragment.
    'max_fragment_records' => 1000,

    // Maximum ZIP parts per transfer.
    'max_parts' => 10000,

    // Maximum allowed ZIP compression ratio.
    'max_compression_ratio' => 1000,

    // Minimum free disk reserve in bytes.
    'disk_reserve_bytes' => 64 * 1024 * 1024,

    // Records processed per analysis batch.
    'analysis_batch_records' => 100,

    // Review items applied per batch.
    'apply_batch_items' => 100,

    // Database records processed per batch.
    'database_batch_records' => 250,
];
