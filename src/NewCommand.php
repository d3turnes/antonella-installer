<?php

namespace D3turnes\AntonellaInstaller\Console;

use ZipArchive;
use RuntimeException;
use GuzzleHttp\Client;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Plugin For Antonella Framework.')
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
			->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Install a specific branch');
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        
		$branches = $this->getAllBranches();
		
		$version = $this->getVersion($input);												// develop or master
		
		$name = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-',$input->getArgument('name'))));
		
		if ($input->getOption('branch')) $version = $this->getBranch($input, $branches); 	// branch
		
		if ( $cmd = exec("git --version 2>&1") ) {
			// clone from git
			$cmd = sprintf('git clone --branch %1$s https://github.com/cehojac/antonella-framework-for-wp "%2$s"', $version, $name);
			system("$cmd");
		}
		else {
			// download from git
			if (! class_exists('ZipArchive')) {
				throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
			}	
		
			$this->verifyApplicationDoesntExist(
				$directory = str_replace('\\', '/', getcwd().'/'.$name),
				$output
			);
			
			$this->download($zipFile = $this->makeFilename(), $version)
				 ->extract($zipFile, str_replace('\\', '/', getcwd()))
				 ->rename($name, $version)
				 ->cleanUp($zipFile);
		}
		
		$composer = $this->findComposer();

        $commands = [
			$composer.' install --no-scripts',
            $composer.' run-script post-create-project-cmd',
        ];

        $process = new Process(implode(' && ', $commands), $name, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd().'/antonella-framework-for-wp_'.md5(time().uniqid()).'.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @param  string  $version
     * @return $this
     */
    protected function download($zipFile, $version = 'master')
    {
        
		$filename = $version . '.zip';

        $response = (new Client([
			'verify' => false
		]))->get('https://github.com/cehojac/antonella-framework-for-wp/archive/refs/heads/'.$filename);
		
        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

	/**
	 *	Rename dir antonella-framework-for-wp-{$version} to plugin-name
	 *	
	 *	@param string $name The Plugin Name
	 *	@param string $version Version, by default master
	 */
	protected function rename($name, $version = 'master') {
		
		$folder = sprintf('antonella-framework-for-wp-%1$s', $version);
		
		if (file_exists($folder) && is_dir($folder))
			rename($folder, $name);		
		
		return $this;
	}
	
    /**
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);

        @unlink($zipFile);

        return $this;
    }

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
	 * @return string
     */
    protected function getVersion($input)
    {
        if ($input->getOption('dev')) {
            return 'develop';
        }
		return 'master';
    }
	
	/**
	 * Download a specific branch
	 *
	 * @param \Symfony\Component\Console\Input\InputInterface  $input
	 * @param Array[] $branches
	 * @return string
	 */
	protected function getBranch($input, $branches) 
	{
		if ( $input->getOption('branch') && in_array($input->getOption('branch'), $branches) ) {
			return $input->getOption('branch');	 
		}
		return 'master';
	}

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }

        return 'composer';
    }
	
	/**
	 *	return All Branches
	 */
	protected function getAllBranches() {
		// 'https://api.github.com/repos/cehojac/antonella-framework-for-wp/branches';
		
		$client = new Client([
			'base_uri' => 'https://api.github.com/repos/cehojac/antonella-framework-for-wp/',
			'verify' => false
		]);
		
		$request = $client->request('GET', 'branches');
		
		$response = json_decode($request->getBody(), true);
		
		$versions = [];
		foreach ($response as $resp) $versions[] = $resp['name'];
		
		return $versions;
	}
}
