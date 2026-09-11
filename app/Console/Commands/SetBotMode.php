<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Bascule keyword ↔ ai à chaud, sans reconstruire l'image.
 */
class SetBotMode extends Command
{
    protected $signature = 'bot:mode {mode : keyword ou ai}';

    protected $description = 'Change le mode du bot dans .env et vide le cache de configuration.';

    public function handle(): int
    {
        $mode = strtolower((string) $this->argument('mode'));

        if (! in_array($mode, ['keyword', 'ai'], true)) {
            $this->components->error("Mode inconnu : {$mode}. Attendu : keyword ou ai.");

            return self::FAILURE;
        }

        $path = app()->environmentFilePath();

        if (! is_writable($path)) {
            $this->components->error("Impossible d'écrire dans {$path}.");

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($path);

        $contents = preg_match('/^BOT_MODE=.*$/m', $contents)
            ? preg_replace('/^BOT_MODE=.*$/m', "BOT_MODE={$mode}", $contents)
            : rtrim($contents, "\r\n")."\nBOT_MODE={$mode}\n";

        file_put_contents($path, $contents);

        $this->callSilent('config:clear');

        // Le worker a chargé la configuration à son démarrage : sans ce signal,
        // il continuerait à répondre avec l'ancien driver.
        $this->callSilent('queue:restart');

        $this->components->info("Mode du bot : {$mode}.");
        $this->components->warn('Le worker redémarre ; laissez-lui deux secondes avant le prochain message.');

        return self::SUCCESS;
    }
}
