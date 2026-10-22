<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CategoryReview;
use App\Support\Categories\CategoryReviewer;
use Illuminate\Console\Command;

/** Checks listing categories from the command line (same engine as Admin → Smart categories). */
class CheckCategoriesCommand extends Command
{
    protected $signature = 'daleel:categories
        {--scope=unchecked : unchecked, other, unsure or all}
        {--ai : Ask the AI for a second opinion on unclear listings}
        {--apply-above= : Also apply suggestions with at least this confidence (e.g. 90)}';

    protected $description = 'Check which category each listing belongs to and propose corrections';

    public function handle(CategoryReviewer $reviewer): int
    {
        $scope = (string) $this->option('scope');
        if (! in_array($scope, CategoryReviewer::SCOPES, true)) {
            $this->error('Unknown scope. Use: '.implode(', ', CategoryReviewer::SCOPES));

            return self::INVALID;
        }

        $cursor = 0;
        $checked = 0;
        $suggested = 0;
        do {
            $step = $reviewer->scan($scope, $cursor, (bool) $this->option('ai'), budgetSeconds: 30.0, limit: 1000);
            $cursor = $step['cursor'];
            $checked += $step['processed'];
            $suggested += $step['suggested'];
            $this->line("Checked {$checked} · corrections {$suggested}");
        } while (! $step['done'] && $step['processed'] > 0);

        if (($min = (int) $this->option('apply-above')) > 0) {
            $min = max(CategoryReview::MEDIUM, min(99, $min));
            $applied = 0;
            CategoryReview::query()->proposed()->where('confidence', '>=', $min)->with('business')
                ->chunkById(200, function ($reviews) use ($reviewer, &$applied) {
                    $applied += $reviewer->applyMany($reviews, null);
                });
            $this->info("Applied {$applied} corrections with confidence ≥ {$min}%.");
        }

        $this->table(['checked', 'proposed', 'high', 'unclear', 'correct', 'applied'], [array_intersect_key($reviewer->stats(), array_flip(['checked', 'proposed', 'high', 'unclear', 'correct', 'applied']))]);

        return self::SUCCESS;
    }
}
