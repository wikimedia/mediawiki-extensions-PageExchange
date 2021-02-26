<?php

class PXInstallPackageAPI extends ApiBase {

	private $mInstalledExtensions = [];

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$packageID = $params['packageid'];
		$user = $this->getUser();

		if ( $packageID == null ) {
			throw new MWException( 'Error: packageid cannot be null' );
		}

		// Extensions loaded via wfLoadExtension().
		$registeredExtensions = ExtensionRegistry::getInstance()->getAllThings();
		foreach ( $registeredExtensions as $extName => $extData ) {
			// Make the names "space-insensitive".
			$extensionName = str_replace( ' ', '', $extName );
			$this->mInstalledExtensions[] = $extensionName;
		}

		// For MW 1.35+, this only gets extensions that are loaded the
		// old way, via include_once() or require_once().
		$extensionCredits = $this->getConfig()->get( 'ExtensionCredits' );
		foreach ( $extensionCredits as $group => $exts ) {
			foreach ( $exts as $ext ) {
				// Make the names "space-insensitive".
				$extensionName = str_replace( ' ', '', $ext['name'] );
				$this->mInstalledExtensions[] = $extensionName;
			}
		}
		$package = $this->getRemotePackage( $packageID );
		if($package == null){
			throw new MWException( 'Error: No Package with ID: "' . $packageID . '" exists.' );
		}
		$package->install( $user );
	}

	private function getRemotePackage( $packageID ) {
		$packageFiles = $this->getConfig()->get( 'PageExchangePackageFiles' );

		$dbr = wfGetDb( DB_REPLICA );
		$res = $dbr->select(
			'px_packages',
			'pxp_global_id'
		);
		$installedPackageIDs = [];
		while ( $row = $res->fetchRow() ) {
			$installedPackageIDs[] = $row[0];
			if ( $row[0] == $packageID ) {
				throw new MWException( 'Package with ID: "' . $packageID . '" is already installed' );
			}
		}

		foreach ( $packageFiles as $url ) {
			$pxFile = PXPackageFile::init( $url, -1, $this->mInstalledExtensions, $installedPackageIDs );
			$packages = $pxFile->getAllPackages( $this->getUser() );
			foreach ( $packages as $package ) {
				if ( $package->getGlobalID() == $packageID ) {
					return $pxFile->getPackage( $package->getName(), $this->getUser() );
				}
			}
		}
	}

	protected function getAllowedParams() {
		return [
			'packageid' => null
		];
	}

	protected function getParamDescription() {
		return [
			'packageid' => 'The globalID of a package'
		];
	}

	protected function getDescription() {
		return 'Installing a package. Defined by https://www.mediawiki.org/wiki/Extension:Page_Exchange';
	}

	protected function getExamples() {
		return [
			'api.php?action=pxinstallpackage&packageid=com.wikiworks.books-demo-cargo'
		];
	}

}
