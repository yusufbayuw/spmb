<?php

/**
 * Exact aliases for the export-only school recap.
 *
 * Keys and values are already uppercase, punctuation/whitespace-normalized
 * labels. These are known variations from the provided school recap example.
 * Add aliases only after verifying they refer to the very same school; never
 * use substring/fuzzy matching for this purpose. In particular, an ambiguous
 * abbreviation such as "SMPN2" must not be assigned to a city by guesswork.
 *
 * The original registration values are not changed by this configuration.
 */
return [
    'aliases' => [
        'TARUNA BAKTI' => 'SMP TARUNA BAKTI',
        'TARUNA BAKTI BANDUNG' => 'SMP TARUNA BAKTI',
        'MUTIARA BUNDA' => 'SMP MUTIARA BUNDA',
        'SMP SALMAN ALFARISI' => 'SMP SALMAN AL FARISI',
        'SALMAN ALFARISI' => 'SMP SALMAN AL FARISI',
        'CEDEKIA LEADERSHIP SCHOOL' => 'CENDEKIA LEADERSHIP SCHOOL',
        'AL AZHAR 36 BANDUNG' => 'SMP ISLAM AL AZHAR 36 BANDUNG',
        'SMP AL AZHAR 36 BANDUNG' => 'SMP ISLAM AL AZHAR 36 BANDUNG',
        'SMP TUNAS UNGGUL BANDUNG' => 'SMP TUNAS UNGGUL',
        'SMP S PUPUK KUJANG' => 'SMP PUPUK KUJANG',
        'SMP PUPUK KUJANG CIKAMPEK' => 'SMP PUPUK KUJANG',
        'SMP DARUL HIKAM UNGGULAN BANDUNG' => 'SMP DARUL HIKAM',
        'SMP DARUL HIKAM INTEGRATED SCHOOL LODAYA' => 'SMP DARUL HIKAM INTEGRATED SCHOOL',
        'DARUL HIKAM INTEGRATED SCHOOL' => 'SMP DARUL HIKAM INTEGRATED SCHOOL',
        'SEKOLAH HAMIDAH SAMPURNA' => 'SMPIT HAMIDAH SAMPURNA',
        'SMP MUHAMADIYAH 8 BANDUNG' => 'SMP MUHAMMADIYAH 8 BANDUNG',
        'AL IRSYAD SATYA' => 'SMP AL IRSYAD SATYA',
        'AL IRSYAD SATYA KBP' => 'SMP AL IRSYAD SATYA',
        'SMP AL IRSYAD SATYA KOTA BARU PARAHYANGAN' => 'SMP AL IRSYAD SATYA',
    ],
];
