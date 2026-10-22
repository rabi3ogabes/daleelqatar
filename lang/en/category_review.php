<?php

return [
    'nav' => 'Smart categories',
    'title' => 'Smart categories',
    'subtitle' => 'Every listing checked against the directory’s categories, with clear reasons for each correction.',

    'stats' => [
        'checked' => 'Checked',
        'checked_hint' => ':unchecked not checked yet',
        'proposed' => 'Corrections proposed',
        'proposed_hint' => 'Waiting for your decision',
        'high' => 'Ready to apply',
        'high_hint' => ':percent% confidence or more',
        'correct' => 'Already correct',
        'correct_hint' => 'No change needed',
        'unclear' => 'Need a decision',
        'unclear_hint' => 'Not enough information',
        'applied' => 'Corrections applied',
        'applied_hint' => ':kept kept as they were',
    ],

    'scan' => [
        'title' => 'Check categories',
        'subtitle' => 'Reads each listing’s name (English and Arabic), its categories on other directories, services and description, and proposes the right category. Nothing changes until you apply it.',
        'scope' => 'Which listings',
        'scopes' => [
            'unchecked' => 'Not checked yet',
            'other' => 'In “Other businesses”',
            'unsure' => 'Unsure results (ask the AI)',
            'all' => 'Everything (re-check)',
        ],
        'ai' => 'Ask the AI for a second opinion on unclear listings',
        'ai_hint' => 'Claude reviews the listings the rules are unsure about. Each answer comes with its reason.',
        'ai_missing' => 'Add ANTHROPIC_API_KEY to enable the AI second opinion. The rules work without it.',
        'start' => 'Start check',
        'stop' => 'Stop',
        'keep_open' => 'Keep this page open while the check runs.',
        'idle' => 'Ready',
        'running' => 'Checking listings…',
        'finished' => 'Check finished',
        'processed' => 'Checked',
        'suggested' => 'Corrections',
        'by_ai' => 'AI opinions',
        'reload' => 'Show the results',
    ],

    'confident' => [
        'title' => 'Apply all confident corrections',
        'text' => ':count corrections have :percent% confidence or more. Applying them moves each listing to its suggested category; every change is recorded in the audit log.',
        'button' => 'Apply :count corrections',
        'confirm' => 'Apply all :count high-confidence corrections now?',
        'running' => 'Applying… :count done',
        'finished' => ':count corrections applied.',
        'none' => 'No high-confidence corrections are waiting.',
    ],

    'moves' => [
        'title' => 'Biggest moves',
        'subtitle' => 'The most common corrections. Review a group, or apply its confident ones in one go.',
        'listings' => '{1} 1 listing|[2,*] :count listings',
        'confident' => ':count confident',
        'review' => 'Review',
        'apply_confident' => 'Apply :count',
        'confirm' => 'Move :count listings from “:from” to “:to”?',
        'none' => 'No corrections are waiting.',
        'nowhere' => 'No category',
    ],

    'tabs' => [
        'proposed' => 'Corrections',
        'unclear' => 'Need a decision',
        'applied' => 'Applied',
        'kept' => 'Kept',
    ],

    'filters' => [
        'search' => 'Search by name',
        'level' => 'Confidence',
        'any_level' => 'Any confidence',
        'from' => 'Current category',
        'to' => 'Suggested category',
        'any' => 'Any',
        'apply' => 'Filter',
        'clear' => 'Clear',
    ],

    'levels' => [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],

    'kinds' => [
        'fix' => 'Wrong category',
        'refine' => 'More specific',
    ],

    'table' => [
        'business' => 'Business',
        'change' => 'Current → suggested',
        'why' => 'Why',
        'confidence' => 'Confidence',
        'decided' => 'Decided',
    ],

    'fields' => [
        'label' => 'Listed elsewhere as',
        'name' => 'Name',
        'name_ar' => 'Arabic name',
        'tagline' => 'Tagline',
        'services' => 'Services',
        'description' => 'Description',
        'ai' => 'AI',
        'admin' => 'Admin',
    ],

    'also' => 'Also fits: :categories',
    'apply' => 'Apply',
    'keep' => 'Keep current',
    'change' => 'Choose another',
    'choose' => 'Choose a category…',
    'move' => 'Move',
    'select_all' => 'Select all on this page',
    'selected' => ':count selected',
    'apply_selected' => 'Apply selected',
    'keep_selected' => 'Keep selected',
    'confirm_bulk' => 'Apply the suggested category to the selected listings?',
    'by' => 'by :name',
    'automatic' => 'automatically',

    'applied_one' => '“:name” moved to its new category.',
    'kept_one' => '“:name” keeps its current category.',
    'applied_many' => '{0} Nothing was applied.|{1} 1 correction applied.|[2,*] :count corrections applied.',
    'kept_many' => '{1} 1 listing keeps its category.|[2,*] :count listings keep their categories.',
    'already_done' => 'This listing was already decided.',
    'pick_category' => 'Choose a category first.',

    'empty' => [
        'proposed' => 'No corrections waiting',
        'proposed_text' => 'Run a check to review the categories of your listings.',
        'unclear' => 'Nothing needs a decision',
        'unclear_text' => 'Every checked listing has a clear category.',
        'applied' => 'No corrections applied yet',
        'applied_text' => 'Applied corrections appear here.',
        'kept' => 'Nothing kept yet',
        'kept_text' => 'Listings you keep in their current category appear here.',
        'filtered' => 'No results for these filters',
    ],

    'panel' => [
        'title' => 'Category check',
        'suggested' => 'Suggested category',
        'correct' => 'The category looks right.',
        'not_checked' => 'Not checked yet.',
        'unclear' => 'Not enough information to tell. Choose a category by editing the listing.',
        'check' => 'Check category',
        'recheck' => 'Check again',
        'open' => 'Open in Smart categories',
    ],

    'check_found' => 'A better category was found: :category. Review it below.',
    'check_ok' => 'The category looks right.',
];
