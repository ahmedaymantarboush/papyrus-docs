<?php

namespace AhmedTarboush\PapyrusDocs\Console\Commands;

use Illuminate\Console\Command;
use AhmedTarboush\PapyrusDocs\PapyrusGenerator;
use AhmedTarboush\PapyrusDocs\Exporters\OpenApiGenerator;
use AhmedTarboush\PapyrusDocs\Exporters\PostmanGenerator;

class PapyrusExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'papyrus:export {--type=openapi : The type of export (openapi or postman)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export the Papyrus API documentation to a JSON file (OpenAPI or Postman)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(PapyrusGenerator $generator)
    {
        $type = strtolower($this->option('type'));

        $this->info("Scanning routes and generating schema...");
        $schema = $generator->scan();

        $content = '';
        $defaultPath = '';

        if ($type === 'postman') {
            $this->info("Generating Postman Collection v2.1...");
            $collection = (new PostmanGenerator())->generate($schema);
            $content = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $defaultPath = 'postman-collection.json';
        } else {
            $this->info("Generating OpenAPI 3.0 Specification...");
            $spec = (new OpenApiGenerator())->generate($schema);
            $content = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $defaultPath = config('papyrus.export_path', 'api.json');
        }

        $path = base_path($defaultPath);

        file_put_contents($path, $content);

        $this->info("Successfully exported documentation to: {$path}");

        return Command::SUCCESS;
    }
}
