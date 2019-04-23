<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class GenerateCombination extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'generate {name?} {--numbers=} {--tickets=} {--perticket=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generate combinations for lotteries.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $filename = $this->getFilename();
        $numbers = $this->getNumbers();
        $maxCombinations = $this->getTickets();
        $totalPerTicket = $this->getNumbersPerTicket();

        // Get all possible combinations...
        $allCombinations = $this->sampling($numbers, $totalPerTicket)->sortBy(function ($combo) {
            return $combo->first() . $combo->last();
        });

        // Separe combinations in chunks of $maxCombinations, because we're not
        // going to use all combinations (probably).
        $chunkSize = ceil($allCombinations->count() / $maxCombinations);
        $groups = $allCombinations->chunk($chunkSize);

        // Get one random combination for every chunk.
        $combos = $groups->map(function ($group) use ($chunkSize) {
            $index = rand(0, $chunkSize - 1);

            return $group->values()->get($index);
        });

        $this->writeCsv($filename, $combos);
    }

    public function sampling(Collection $numbers, int $size) {
        // If the length of the combination is 1 then each element of the original array
        // is a combination itself.
        if ($size === 1) {
            return $numbers->map(function ($char) {
                return new Collection($char);
            });
        }

        $combos = new Collection;

         // Extract characters one by one and concatenate them to combinations of smaller lengths.
        // We need to extract them because we don't want to have repetitions after concatenation.
        $numbers->each(function($number, $index) use ($numbers, $size, $combos) {
            $smallerCombos = $this->sampling($numbers->slice($index + 1)->values(), $size - 1);

            $smallerCombos->each(function ($smallerCombo) use ($combos, $number) {
                $combos->push((new Collection($number))->concat($smallerCombo->toArray()));
            });
        });

        return $combos;
    }

    /**
     * Generate the CSV file with generated combinations.
     *
     * @param string $filename
     * @param Collection $data
     * @return void
     */
    public function writeCsv(string $filename, Collection $data)
    {
        $date = Carbon::now()->format('Y-m-d-Hms');

        $contents = $data->map(function ($numbers, $row) {
            $combName = 'COMB-' . ($row + 1);

            return implode(',', array_merge([$combName], $numbers->toArray() ?? []));
        });

        Storage::put("exports/{$filename}__{$date}.csv", $contents->implode(PHP_EOL));
    }

    /**
     * Get the file name for the generated file.
     *
     * @return string
     */
    protected function getFilename(): string
    {
        if ($name = $this->argument('name')) {
            return $name;
        }

        return $this->ask('Como você deseja salvar o arquivo?', "loteria");
    }

    /**
     * Get the numbers to be used on combinations.
     *
     * @return Collection
     */
    protected function getNumbers(): Collection
    {
        $numbers = is_numeric($this->option('numbers'))
            ? $this->option('numbers')
            : $this->ask("Quais são os números utilizados na combinação? (Separe por espaço)");

        return new Collection(explode(' ', $numbers));
    }

    /**
     * Get how many tickets must be generated.
     *
     * @return int
     */
    protected function getTickets(): int
    {
        if (is_numeric($this->option('tickets'))) {
            return intval($this->option('tickets'));
        }

        return intval($this->ask('Quantas combinações você deseja gerar?'));
    }

    /**
     * Get how many nummbers must me added for every ticket.
     *
     * @return int
     */
    protected function getNumbersPerTicket(): int
    {
        if (is_numeric($this->option('perticket'))) {
            return intval($this->option('perticket'));
        }

        return intval($this->ask('Quantos números devem haver em cada combinação?'));
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
