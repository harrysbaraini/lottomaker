<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class MostCommonNumbers extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'analyze:common-numbers';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $processed = new Collection;

         $this->getResults()->each(function ($g) use ($processed) {
            return Collection::make($g)->each(function ($value) use ($processed) {
                $total = intval($processed->get($value, 0) + 1);
                
                $processed->put($value, $total);
            });
        });

        // Get most common numbers...
        $this->info('=============================================' . PHP_EOL . 'Most common' . PHP_EOL .'=============================================');

        $processed = $processed->sortByDesc(function ($v) {
            return $v;
        })->map(function ($amount, $num) {
            $this->comment($num . '     ' . $amount . ' x ');

            return [
                "number" => (string)$num,
                "amount" => $amount,
            ];
        })->values();

        $this->info(PHP_EOL);

        // Get all non used numbers ...
        $notUsed = Collection::make([
            '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25',
        ])->diff($processed->pluck('number')->toArray())->sortByDesc(function ($v) {
            return $v;
        })->map(function ($num) {
            return [
                "number" => (string)$num,
                "amount" => 0,
            ];
        })->values();

        [$mostSelected, $others] = $processed->partition(function ($n, $index) {
            return $index < 3;
        });

        [$lessSelected, $others] = $others->reverse()->values()->partition(function ($n, $index) {
            return $index < 3;
        });

        [$middleSelected, $others] = $others->values()->partition(function ($n, $index) {
            return $index >= 3 && $index <= 5;
        });

        $fixed = $mostSelected
            ->concat($lessSelected)
            ->concat($middleSelected)
            ->sortBy(function ($v) {
                return $v;
            });

        $complementary = $others
            ->values()
            ->concat($notUsed)
            ->sortBy('amount')
            ->chunk(3)->map(function ($group) {
                return $group;
            });

        $news = clone $fixed;

        $complementary->each(function ($c) use ($news) {
            if ($news->count() < 15) {
                $news->push($c->first());
            }
        });

        // Groups of 5
        $this->info('=============================================' . PHP_EOL . 'Groups' . PHP_EOL .'=============================================');

        $groups5 = $processed->mapToGroups(function ($g) {
            return [ceil($g['number'] / 5) => $g];
        })->mapWithKeys(function ($group, $key) {
            return [
                $key => array_merge($group->toArray(), [
                    'total' => $group->sum('amount'),
                ])
            ];
        })->sortByDesc('total')->each(function ($group, $index) {
            $start = $index * 5 - 4;
            $end = $start + 4;
            $this->info($group['total'] . "     =>      {$start} - {$end}");
        });

        $this->comment('');

        // Final game
        $this->info('=============================================' . PHP_EOL . 'Final Numbers' . PHP_EOL .'=============================================');
        $this->comment($news->sortBy('number')->pluck('number'));
    }
    
    /**
     * Get the file name for the generated file.
     *
     * @return string
     */
    protected function getResults(): Collection
    {
        $json = json_decode(Storage::get('data.json'));

        return new Collection($json->results);
    }
}
