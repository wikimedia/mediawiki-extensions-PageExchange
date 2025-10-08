<?php

$IP = getenv( 'MW_INSTALL_PATH' ) ?: __DIR__ . '/../../..';
if ( !is_readable( "$IP/maintenance/Maintenance.php" ) ) {
	die( "MW_INSTALL_PATH needs to be set to your MediaWiki installation.\n" );
}
require_once "$IP/maintenance/Maintenance.php";

class ExportPages extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'This script exports selected pages into a git-ready structure' );
		$this->addOption( 'out', 'directory to save exported pages or json filename if exporting JSON', true, true, 'o' );
		$this->addOption( 'category', 'category to be exported', false, true, 'c' );
		$this->addOption( 'pagelist', 'file with pages to be exported', false, true, 'l' );
		$this->addOption( 'full', 'export the whole wiki', false, false, 'f' );

		$this->addOption( 'json', 'export as JSON compatible with PageExchange', false, false, 'j' );
		$this->addOption( 'package', 'package name (JSON only)', false, true, 'p' );
		$this->addOption( 'desc', 'package description (JSON only)', false, true, 'd' );
		$this->addOption( 'github', 'github repository name to use in JSON export urls', false, true, 'g' );
		$this->addOption( 'url', 'url to use in JSON export urls (non-github)', false, true, 'r' );
		$this->addOption( 'version', 'JSON package version', false, true, 'v' );
		$this->addOption( 'author', 'JSON package author', false, true, 'a' );
		$this->addOption( 'publisher', 'JSON package publisher', false, true, 'u' );
		$this->addOption( 'directoryStructure', 'JSON package directoryStructure', false, false, 's' );

		$this->addOption( 'dependencies', 'List of dependencies (packages) to include (separated by comma)', false, true, 'd' );
		$this->addOption( 'extensions', 'List of dependencies (extensions) to include (separated by comma)', false, true, 'e' );

		$this->addOption( 'clean', 'Wipe the destination directory first', false, false, 'x' );

		$this->requireExtension( 'Page Exchange' );
	}

	/**
	 * @return null
	 */
	public function execute() {
		$json = $this->getOption( 'json' );

		if ( ( !$json && !is_dir( $this->getOption( 'out' ) ) ) ||
			 !is_writable( dirname( $this->getOption( 'out' ) ) ) ) {
			$this->fatalError(
				'Output directory does not exist or you have no write permissions' );
		}

		if ( !$this->getOption( 'category' ) && !$this->getOption( 'pagelist' ) &&
			 !$this->getOption( 'full' ) ) {
			$this->fatalError(
				'Either --full, --category or --pagelist parameter need to be specified.' );
		}

		if ( $this->getOption( 'category' ) && $this->getOption( 'pagelist' ) &&
			 $this->getOption( 'full' ) ) {
			$this->fatalError(
				'Either --full, --category or --pagelist parameter need to be specified.' );
		}

		$pages = [];
		$root = $this->getOption( 'out' );
		$category = $this->getOption( 'category' );
		$pagelist = $this->getOption( 'pagelist' );
		$full = $this->getOption( 'full' );
		$package = $this->getOption( 'package', null );
		$desc = $this->getOption( 'desc', '' );
		$github = $this->getOption( 'github' );
		$url = $this->getOption( 'url' );
		$version = $this->getOption( 'version' );
		$author = $this->getOption( 'author' );
		$publisher = $this->getOption( 'publisher' );
		$dependencies = $this->getOption( 'dependencies' );
		$extensions = $this->getOption( 'extensions' );
		$directoryStructure = $this->getOption( 'directoryStructure' );

		if ( $dependencies ) {
			$dependencies = explode( ',', $dependencies );
		}
		if ( $extensions ) {
			$extensions = explode( ',', $extensions );
		}

		$exportPackage = new PXExportPackage(
			$package,
			$desc,
			$github,
			$url,
			$version,
			$author,
			$publisher,
			$extensions,
		);

		if ( $category ) {
			if ( !$exportPackage->addCategory( $category ) ) {
				$this->fatalError( 'The category specified does not exist.' );
			}
		}
		if ( $pagelist ) {
			if ( !file_exists( $pagelist ) ) {
				$this->fatalError(
					'The pagelist file does not exist or you have no read permission.' );
			}
			$pages = file( $pagelist, FILE_IGNORE_NEW_LINES );
			$exportPackage->addPages( $pages );
		}
		if ( $full ) {
			$exportPackage->addAllPages();
		}
		if ( !count( $exportPackage->getPages() ) ) {
			$this->fatalError( 'There is nothing to export!' );
		}

		try {
			if ( $json ) {
				$exportPackage->exportJSON( $root, $directoryStructure, true );
			} else {
				if ( $this->getOption( 'clean' ) ) {
					// get all file names
					$files = glob( $root . '/**/*' );
					// iterate files
					foreach ( $files as $file ) {
						if ( $file == '.' || $file == '..' ) {
							continue;
						}
						if ( is_file( $file ) ) {
							// delete file
							unlink( $file );
						}
						if ( is_dir( $file ) ) {
							rmdir( $file );
						}
					}
				}
				$exportPackage->exportToDirectory( $root );
			}
		} catch ( Exception $e ) {
			$this->fatalError( $e->getMessage() );
		}

		$this->output( "Done!\n" );
	}

}

$maintClass = ExportPages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
