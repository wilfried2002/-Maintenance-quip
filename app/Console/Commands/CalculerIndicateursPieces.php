<?php

namespace App\Console\Commands;

use App\Services\IndicateurPerformanceCalculator;
use Illuminate\Console\Command;

class CalculerIndicateursPieces extends Command
{
    protected $signature = 'indicateurs:calculer';

    protected $description = 'Recalcule les indicateurs de performance des pièces à partir des consommations réelles';

    public function handle(IndicateurPerformanceCalculator $calculator): int
    {
        $count = $calculator->calculerTout();

        $this->info("Indicateurs recalculés pour {$count} pièce(s).");

        return self::SUCCESS;
    }
}
