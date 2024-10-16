<?php

namespace Fuelviews\SabHero\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SabHeroCommand extends Command
{
    protected $signature = 'sab-hero:install {--force : Overwrite any existing files}';

    protected $description = 'Install Fuelviews packages, install TailwindCSS and Vite';

    /**
     * @throws JsonException
     */
    public function handle(): void
    {
        $this->installFuelviewsPackages();
        $this->installTailwindCss();
        $this->installVite();
        $this->info('All packages and dependencies installed successfully.');
    }

    private function installFuelviewsPackages(): void
    {
        $packages = [
            'fuelviews/laravel-layouts-wrapper' => '^0.0',
            'fuelviews/laravel-cloudflare-cache' => '^0.0',
            'fuelviews/laravel-robots-txt' => '^0.0',
            'fuelviews/laravel-sitemap' => '^0.0',
            'fuelviews/laravel-cpanel-auto-deploy' => '^0.0',
            'fuelviews/laravel-navigation' => '^0.0',
            'fuelviews/laravel-forms' => '^0.0',
            'ralphjsmit/laravel-seo' => '^1.6',
            'spatie/laravel-medialibrary' => '^11.0',
        ];

        $requireCommand = 'composer require';
        foreach ($packages as $package => $version) {
            $requireCommand .= " {$package}:{$version}";
        }

        $this->info('Installing Fuelviews packages...');
        $this->runShellCommand($requireCommand);

        $this->info('Running package-specific install commands...');

        $force = $this->option('force') ? '--force' : '';

        $this->runShellCommand("php artisan vendor:publish --tag=cloudflare-cache-config {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=sitemap-config {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=navigation-config {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=navigation-logo {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=forms-config {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=layouts-wrapper-seeders {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=layouts-wrapper-models {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=layouts-wrapper-welcome {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=seo-migrations {$force}");
        $this->runShellCommand("php artisan vendor:publish --tag=seo-config {$force}");
        $this->runShellCommand("php artisan vendor:publish --provider='Spatie\MediaLibrary\MediaLibraryServiceProvider' --tag=medialibrary-migrations {$force}");

        $this->runShellCommand('php artisan layouts-wrapper:install');
        $this->runShellCommand('php artisan navigation:install');
        $this->runShellCommand("php artisan forms:install {$force}");
        $this->runShellCommand('php artisan deploy:install || true');

        $this->runShellCommand('php artisan storage:link');
        $this->runShellCommand("php artisan migrate {$force}");

        $this->updateComposerScripts(function ($scripts) {
            if (! isset($scripts['format'])) {
                $scripts['format'] = ['vendor/bin/pint'];
            }

            return $scripts;
        });
    }

    /**
     * @throws JsonException
     */
    private function installTailwindCss(): void
    {
        $force = $this->option('force');

        $this->publishConfig('tailwind.config.js', $force);
        $this->publishConfig('postcss.config.js', $force);
        $this->publishConfig('.prettierrc', $force);
        $this->publishConfig('.prettierignore', $force);
        $this->publishAppCss($force);

        $devDependencies = [
            '@tailwindcss/forms',
            '@tailwindcss/typography',
            'autoprefixer',
            'postcss',
            'tailwindcss',
            'prettier',
            'prettier-plugin-blade',
            'prettier-plugin-tailwindcss',
        ];

        $this->installNodePackages($devDependencies);

        // Add Prettier format script to package.json
        $this->updateNodeScripts(function ($scripts) {
            $newScripts = [];
            foreach ($scripts as $key => $value) {
                $newScripts[$key] = $value;
                if ($key === 'build') {
                    $newScripts['format'] = 'npx prettier --write resources/views/';
                }
            }

            return $newScripts;
        });
    }

    /**
     * @throws JsonException
     */
    private function installVite(): void
    {
        $this->publishConfig('vite.config.js');

        $devDependencies = [
            'dotenv',
            'laravel-vite-plugin',
            'vite',
        ];

        $this->installNodePackages($devDependencies);
    }

    private function updateComposerScripts(callable $callback): void
    {
        $composerJsonPath = base_path('composer.json');

        if (! file_exists($composerJsonPath)) {
            $this->warn('composer.json file not found.');

            return;
        }

        $content = json_decode(file_get_contents($composerJsonPath), true);

        $content['scripts'] = $callback(
            array_key_exists('scripts', $content) ? $content['scripts'] : []
        );

        file_put_contents(
            $composerJsonPath,
            json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL
        );

        $this->info('Updated composer.json scripts section.');
    }

    protected function publishConfig(string $configFileName, bool $force = false): void
    {
        $stubPath = __DIR__."/../../stubs/$configFileName.stub";
        $destinationPath = base_path($configFileName);

        if ($force || $this->option('force')) {
            File::copy($stubPath, $destinationPath);
            $this->info("$configFileName has been installed or overwritten successfully.");
        } elseif (File::exists($destinationPath)) {
            if ($this->confirm("$configFileName already exists. Do you want to overwrite it?", false)) {
                File::copy($stubPath, $destinationPath);
                $this->info("$configFileName has been overwritten successfully.");
            } else {
                $this->warn("Skipping $configFileName installation.");
            }
        } else {
            File::copy($stubPath, $destinationPath);
            $this->info("$configFileName has been installed successfully.");
        }
    }

    protected function publishAppCss(bool $force): void
    {
        $stubPath = __DIR__.'/../../stubs/css/app.css.stub';
        $destinationPath = resource_path('css/app.css');

        if (! File::exists(dirname($destinationPath))) {
            File::makeDirectory(dirname($destinationPath), 0755, true);
        }

        if ($force || $this->option('force')) {
            File::copy($stubPath, $destinationPath);
            $this->info('css/app.css file has been installed or overwritten successfully.');
        } elseif (File::exists($destinationPath)) {
            if ($this->confirm('css/app.css already exists. Do you want to overwrite it?', false)) {
                File::copy($stubPath, $destinationPath);
                $this->info('css/app.css file has been overwritten successfully.');
            } else {
                $this->warn('Skipping css/app.css installation.');
            }
        } else {
            File::copy($stubPath, $destinationPath);
            $this->info('css/app.css file has been installed successfully.');
        }
    }

    /**
     * @throws JsonException
     */
    protected function installNodePackages(array $packageNames): void
    {
        $packageJsonPath = base_path('package.json');
        $packageJsonContent = File::get($packageJsonPath);
        $packageJson = json_decode($packageJsonContent, true, 512, JSON_THROW_ON_ERROR);

        $packagesToInstall = [];
        foreach ($packageNames as $packageName) {
            if (! isset($packageJson['devDependencies'][$packageName])) {
                $packagesToInstall[] = $packageName;
            }
        }

        if (! empty($packagesToInstall)) {
            $packageInstallString = implode(' ', $packagesToInstall);
            $command = "npm install $packageInstallString --save-dev";

            $process = Process::fromShellCommandline($command, null, null, STDIN, null);
            $process->setTty(Process::isTtySupported());
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->info('Node packages installed successfully.');
        }
    }

    private function updateNodeScripts(callable $callback)
    {
        $packageJsonPath = base_path('package.json');

        if (! file_exists($packageJsonPath)) {
            $this->warn('package.json file not found.');

            return;
        }

        $content = json_decode(file_get_contents($packageJsonPath), true);

        $content['scripts'] = $callback(
            array_key_exists('scripts', $content) ? $content['scripts'] : []
        );

        file_put_contents(
            $packageJsonPath,
            json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL
        );

        $this->info('Updated package.json scripts section.');
    }

    private function runShellCommand($command): void
    {
        $process = Process::fromShellCommandline($command);

        $process->setTty(Process::isTtySupported());

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
