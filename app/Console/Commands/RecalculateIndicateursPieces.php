<?php

namespace App\Console\Commands;

use App\Services\IndicateurPerformanceCalculator;
use Illuminate\Console\Command;

class RecalculateIndicateursPieces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'indicateurs:recalculate-pieces {--only-organisation= : ID de l\'organisation (par défaut: toutes)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcule les indicateurs de performance des pièces à partir des données réelles des interventions';

    /**
     * Execute the console command.
     */
    public function handle(IndicateurPerformanceCalculator $calculator): int
    {
        $this->info('🔄 Recalcul des indicateurs de performance des pièces...');
        $this->newLine();

        $startTime = microtime(true);

        try {
            $count = $calculator->calculerTout();

            $duration = round(microtime(true) - $startTime, 2);

            $this->info("✅ Succès !");
            $this->line("📊 <fg=green>$count</> pièces ont été mises à jour");
            $this->line("⏱️  Durée : <fg=cyan>{$duration}s</>");
            $this->newLine();

            $this->info('Indicateurs calculés :');
            $this->line('  • Nombre de remplacements (consommations totales)');
            $this->line('  • Durée de vie moyenne (écart entre remplacements)');
            $this->line('  • MTBF - Mean Time Between Failures (heures de service)');
            $this->line('  • Taux de défaillance (% interventions correctives)');
            $this->line('  • Coût total de remplacement');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors du recalcul : ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
