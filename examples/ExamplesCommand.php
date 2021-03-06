<?php

namespace RodrigoPedra\RecordProcessor\Examples;

use PDO;
use ArrayIterator;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use RodrigoPedra\RecordProcessor\ProcessorBuilder;
use Symfony\Component\Console\Input\InputArgument;
use RodrigoPedra\RecordProcessor\Writers\PDOWriter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RodrigoPedra\RecordProcessor\Helpers\Excel\Formats;
use RodrigoPedra\RecordProcessor\Helpers\Writers\WriterConfigurator;
use RodrigoPedra\RecordProcessor\Helpers\Writers\WriterCallbackProxy;
use RodrigoPedra\RecordProcessor\Examples\Loggers\ConsoleOutputLogger;
use RodrigoPedra\RecordProcessor\Examples\RecordObjects\ExampleRecordParser;
use RodrigoPedra\RecordProcessor\Examples\RecordObjects\ExampleRecordFormatter;
use RodrigoPedra\RecordProcessor\Examples\RecordObjects\ExampleRecordAggregateFormatter;

class ExamplesCommand extends Command
{
    protected function configure()
    {
        $this->setName('examples');

        $this->setDescription('Showcases converters usage');

        $this->addArgument('reader', InputArgument::REQUIRED, $this->getAvailableReaders());
        $this->addArgument('writer', InputArgument::REQUIRED, $this->getAvailableWriters());

        $this->addOption('log', 'l', InputOption::VALUE_NONE, 'logs record flow');
        $this->addOption('aggregate', 'a', InputOption::VALUE_NONE, 'aggregates output');
        $this->addOption('invalid', 'i', InputOption::VALUE_NONE, 'log invalid records');
    }

    protected function getAvailableReaders()
    {
        return 'array|collection|csv|excel|iterator|pdo|text';
    }

    protected function getAvailableWriters()
    {
        return 'array|collection|csv|echo|excel|html|json|log|pdo|pdo-buffered|text';
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Script start
        $time_start = microtime(true);
        $logger = new ConsoleOutputLogger($output);

        try {
            $builder = $this->makeBuilder();
            $builder->setLogger($logger);

            if ($input->getOption('invalid')) {
                $builder->logInvalidRecords('INVALID INPUT');
            }

            if ($input->getOption('log')) {
                $builder->logRecords('INPUT');
            }

            if ($input->getOption('aggregate')) {
                $builder->usingFormatter(new ExampleRecordAggregateFormatter);
                $builder->aggregateRecordsByKey();
            } else {
                $builder->usingFormatter(new ExampleRecordFormatter);
            }

            if ($input->getOption('log')) {
                $builder->logRecords('OUTPUT');
            }

            if ($input->getOption('invalid')) {
                $builder->logInvalidRecords('INVALID OUTPUT');
            }

            $builder->usingParser(new ExampleRecordParser);
            $this->readFrom($builder, $input->getArgument('reader'));

            $builder->filterValidRecords();

            $this->writeTo($builder, $input->getArgument('writer'));

            $processor = $builder->build();

            $output = $processor->process();

            $logger->info('input lines: ' . $output->getInputLineCount());
            $logger->info('input records: ' . $output->getInputRecordCount());
            $logger->info('output lines: ' . $output->getOutputRecordCount());
            $logger->info('output records: ' . $output->getOutputRecordCount());

            if ($output->hasOutput()) {
                $logger->info('output: ', Arr::wrap($output->getOutput()));
            }
        } finally {
            $logger->info(sprintf('memory: %.2fMB', floatval(memory_get_peak_usage(true)) / 1024.0 / 1024.0));

            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);

            $logger->info('Total Execution Time: ' . $execution_time . ' seconds');
        }
    }

    protected function makeBuilder()
    {
        return new ProcessorBuilder;
    }

    /**
     * @param  ProcessorBuilder  $builder
     * @param  string  $reader
     * @return mixed
     */
    protected function readFrom($builder, $reader)
    {
        $inputPath = $this->storagePath('input');

        switch ($reader) {
            case 'array':
                return $builder->readFromArray($this->sampleData());
            case 'collection':
                return $builder->readFromCollection(new Collection($this->sampleData()));
            case 'csv':
                return $builder->readFromCSVFile($inputPath . '.csv');
            case 'excel':
                return $builder->readFromExcelFile($inputPath . '.xlsx');
            case 'iterator':
                return $builder->readFromIterator(new ArrayIterator($this->sampleData()));
            case 'pdo':
                $pdo = $this->makeConnection();
                $query = 'SELECT name, email FROM users ORDER BY rowid LIMIT 25';

                return $builder->readFromPDO($pdo, $query);
            case 'text':
                return $builder->readFromTextFile($inputPath . '.txt');
            default:
                throw new InvalidArgumentException('Invalid reader');
        }
    }

    /**
     * @param  ProcessorBuilder  $builder
     * @param  string  $writer
     * @return mixed
     */
    protected function writeTo($builder, $writer)
    {
        $outputPath = $this->storagePath('output');

        switch ($writer) {
            case 'array':
                return $builder->writeToArray();
            case 'collection':
                return $builder->writeToCollection();
            case 'csv':
                return $builder->writeToCSVFile($outputPath . '.csv', function (WriterConfigurator $configurator) {
                    $configurator->setHeader(['name', 'email']);
                });
            case 'echo':
                return $builder->writeToEcho(function (WriterConfigurator $configurator) {
                    $configurator->setPrefix('PERSIST');
                });
            case 'excel':
                return $builder->writeToExcelFile($outputPath . '.xlsx',
                    function (WriterConfigurator $configurator) {
                        $configurator->setHeader(['name', 'email']);

                        $configurator->setTrailler(function (WriterCallbackProxy $proxy) {
                            $proxy->append([$proxy->getRecordCount() . ' records']);
                            $proxy->append([($proxy->getLineCount() + 1) . ' lines']);
                        });

                        $configurator->setWorkbookConfigurator(function ($workbook) {
                            $workbook->setTitle('Workbook title');
                            $workbook->setCreator('Creator');
                            $workbook->setCompany('Company');
                        });

                        $configurator->setWorksheetConfigurator(function ($worksheet) {
                            $worksheet->setTitle('results', false);

                            $worksheet->setColumnFormat([
                                'A' => Formats::text(),
                                'B' => Formats::general(),
                            ]);

                            // header
                            $worksheet->freezeFirstRow();
                            $worksheet->cells('A1:B1', function ($cells) {
                                $cells->setFontWeight('bold');
                                $cells->setBorder('node', 'none', 'solid', 'none');
                            });
                            $worksheet->getStyle('A1:B1')->getNumberFormat()->setFormatCode(Formats::text());
                        });
                    });
            case 'html':
                return $builder->writeToHTMLTable(function (WriterConfigurator $configurator) {
                    $configurator->setHeader(['name', 'email']);
                    $configurator->setTableClassAttribute('table table-condensed');
                    $configurator->setTableIdAttribute('my-table');
                });
            case 'json':
                return $builder->writeToJSONFile($outputPath . '.json');
            case 'log':
                return $builder->writeToLog(function (WriterConfigurator $configurator) {
                    $configurator->setPrefix('PERSIST');
                });
            case 'pdo':
            case 'pdo-buffered':
                $pdo = $this->makeConnection();

                return $builder->writeToPDO($pdo, 'users', ['name', 'email'], $writer === 'pdo-buffered');
            case 'text':
                return $builder->writeToTextFile($outputPath . '.txt');
            default:
                throw new InvalidArgumentException('Invalid writer');
        }
    }

    protected function storagePath($file)
    {
        return __DIR__ . '/../storage/' . $file;
    }

    protected function makeConnection()
    {
        $filePath = $this->storagePath('database.sqlite');

        $path = realpath($filePath);
        $fileExists = $path !== false;

        if (! $fileExists) {
            touch($filePath);
            $path = realpath($filePath);
        }

        $connection = new PDO("sqlite:{$path}", '', '', [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->populateTable($connection, ! $fileExists);

        return $connection;
    }

    protected function populateTable(PDO $connection, $seed)
    {
        $connection->exec('CREATE TABLE IF NOT EXISTS users (name TEXT, email TEXT)');

        if (! $seed) {
            return;
        }

        $writer = new PDOWriter($connection, 'users', ['name', 'email']);
        $writer->setUsesTransaction(true);

        $writer->open();

        $sampleData = $this->sampleData();

        foreach ($sampleData as $values) {
            $writer->append($values);
        }

        $writer->close();
    }

    protected function sampleData()
    {
        return [
            ['Rodrigo', 'rodrigo@example.com'],
            ['Rodrigo', 'rodrigo@example.org'],
            ['Noemi', 'noemi@example.com'],
            ['Bruno', 'bruno@example.org'],
            ['Bruno', 'bruno@example.com'],
        ];
    }
}
