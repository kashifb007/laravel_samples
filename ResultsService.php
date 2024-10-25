<?php

namespace App\Http\Services;

use App\Enums\StatisticType;
use App\Enums\TestStatus;
use App\Enums\VotStatus;
use App\Models\Answer;
use App\Models\Component;
use App\Models\Outlet;
use App\Models\Section;
use App\Models\Statistic;
use App\Models\Test;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ResultsService
{
    public $testStatuses = [TestStatus::Complete->value, TestStatus::Released->value];

    public function calculateIndustryAverageYear(int $year, int $testStatus, int $outletId = null, bool $months = false)
    {
        $monthsString = $months ? '1' : '0';
        $cacheName = 'industry_average_' . $year . $outletId . $monthsString;

        $query = Answer::join('tests', 'tests.id', '=', 'answers.test_id')
            ->join('questions', function (JoinClause $join) {
                $join
                    ->on('questions.id', '=', 'answers.question_id')
                    ->where('questions.scored', '=', 1);
            })
            ->join('outlets', 'outlets.id', '=', 'tests.outlet_id')
            ->join('venues', 'venues.id', '=', 'outlets.venue_id')
            ->whereRaw('YEAR(tests.compared_at) = ?', ['year' => $year])
            ->where('tests.vot_status_id', '=', VotStatus::Contracted->value)
            ->where('tests.test_status_id', '=', $testStatus);

        if (isset($outletId)) {
            $cacheName = 'industry_average_' . $outletId . '-' . $year;
            $query = $query->where('tests.outlet_id', '=', $outletId)
                ->orderBy('tests.compared_at')
                ->selectRaw('ROUND(SUM(answers.score) / SUM(questions.target) * 100,1) AS average');

            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->first();
            });
        } elseif ($months) {
            $query = $query->groupByRaw('MONTH(tests.compared_at)')
                ->orderByRaw('MONTH(tests.compared_at)')
                ->selectRaw(
                    'ROUND(SUM(answers.score) / SUM(questions.target) * 100,1) AS average, MONTH(tests.compared_at) AS compared_at'
                );
            $cacheName = 'industry_average_' . $outletId . '-' . $year . '-months';
            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->get();
            });
        } else {
            $query = Statistic::query()->where('type', '=', StatisticType::Industry)
                ->whereNull('month')
                ->whereNull('section_id')
                ->whereNull('outlet_id')
                ->where('year', '=', $year)
                ->selectRaw('ROUND(score,1) as average');

            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->first() ?? 0;
            });
        }

        return $industryAverageYear;
    }

    public function calculateIndustryAverageYearByProduct(int $year, int $testStatus, int $productId, int $outletId = null, bool $months = false)
    {
        $cacheName = 'industry_average_' . $year . $productId;

        $query = Answer::join('tests', 'tests.id', '=', 'answers.test_id')
            ->join('components', 'components.id', '=', 'tests.component_id')
            ->join('products', function (JoinClause $join) use ($productId) {
                $join
                    ->on('products.id', '=', 'components.product_id')
                    ->where('components.product_id', '=', $productId);
            })
            ->join('questions', function (JoinClause $join) {
                $join
                    ->on('questions.id', '=', 'answers.question_id')
                    ->where('questions.scored', '=', 1);
            })
            ->join('outlets', 'outlets.id', '=', 'tests.outlet_id')
            ->join('venues', 'venues.id', '=', 'outlets.venue_id')
            ->whereRaw('YEAR(tests.compared_at) = ?', ['year' => $year])
            ->where('tests.vot_status_id', '=', VotStatus::Contracted->value)
            ->where('tests.test_status_id', '=', $testStatus);

        if (isset($outletId)) {
            $cacheName = 'industry_average_' . $outletId . '-' . $year;
            $query = $query->where('tests.outlet_id', '=', $outletId)
                ->orderBy('tests.compared_at')
                ->groupBy('products.id', 'answers.score')
                ->selectRaw('products.id, ROUND(SUM(answers.score) / SUM(questions.target) * 100,1) AS average');

            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->first();
            });
        } elseif ($months) {
            $query = $query->groupByRaw('MONTH(tests.compared_at)')
                ->groupBy('products.id', 'answers.score', 'tests.compared_at')
                ->orderByRaw('MONTH(tests.compared_at)')
                ->selectRaw(
                    'products.id, ROUND(SUM(answers.score) / SUM(questions.target) * 100,1) AS average, MONTH(tests.compared_at) AS compared_at'
                );
            $cacheName = 'industry_average_' . $outletId . '-' . $year . '-months';
            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->get();
            });
        } else {
            $query = Statistic::join('components', 'components.id', '=', 'statistics.component_id')
                ->join('products', function (JoinClause $join) use ($productId) {
                    $join
                        ->on('products.id', '=', 'components.product_id')
                        ->where('components.product_id', '=', $productId);
                })
                ->where('type', '=', StatisticType::Industry)
                ->whereNull('month')
                ->whereNull('section_id')
                ->whereNull('outlet_id')
                ->where('year', '=', $year)
                ->groupBy('products.id', 'score')
                ->selectRaw('products.id, ROUND(score,1) as average');

            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->first() ?? 0;
            });
        }

        return $industryAverageYear;
    }

    public function calculateIndustryAverageYearByComponent(int $year, int $testStatus, int $componentId, int $outletId = null, bool $months = false)
    {
        $cacheName = 'industry_average_' . $year;

        $query = Answer::join('tests', 'tests.id', '=', 'answers.test_id')
            ->join('questions', function (JoinClause $join) {
                $join
                    ->on('questions.id', '=', 'answers.question_id')
                    ->where('questions.scored', '=', 1);
            })
            ->join('outlets', 'outlets.id', '=', 'tests.outlet_id')
            ->join('venues', 'venues.id', '=', 'outlets.venue_id')
            ->whereRaw('YEAR(tests.compared_at) = ?', ['year' => $year])
            ->where('tests.vot_status_id', '=', VotStatus::Contracted->value)
            ->where('tests.component_id', $componentId)
            ->where('tests.test_status_id', '=', $testStatus);

        if (isset($outletId)) {
            $cacheName = 'industry_average_' . $outletId . '-' . $year;
            $query = $query->where('tests.outlet_id', '=', $outletId)
                ->orderBy('tests.compared_at')
                ->groupBy('answers.score')
                ->selectRaw('ROUND(SUM(answers.score) / SUM(questions.target) * 100,1) AS average');

            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->first();
            });
        } elseif ($months) {
            $query = $query->groupByRaw('MONTH(tests.compared_at)')
                ->groupBy('answers.score', 'tests.compared_at')
                ->orderByRaw('MONTH(tests.compared_at)')
                ->selectRaw(
                    'ROUND(SUM(answers.score) / SUM(questions.target) * 100,1) AS average, MONTH(tests.compared_at) AS compared_at'
                );
            $cacheName = 'industry_average_' . $outletId . '-' . $year . '-months';
            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->get();
            });
        } else {
            $query = Statistic::query()
                ->where('type', '=', StatisticType::Industry)
                ->whereNull('month')
                ->whereNull('section_id')
                ->whereNull('outlet_id')
                ->where('component_id', '=', $componentId)
                ->where('year', '=', $year)
                ->selectRaw('ROUND(score,1) as average');

            $industryAverageYear = Cache::remember($cacheName, 3600, function () use ($query) {
                return $query->first() ?? 0;
            });
        }

        return $industryAverageYear;
    }

}
