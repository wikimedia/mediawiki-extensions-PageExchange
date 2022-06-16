<?php

class PXUpdatePackageAPI extends ApiBase {

	private $mInstalledExtensions = [];
	private $mInstalledPackages = [];

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$packageID = $params['packageid'];
		$user = $this->getUser();

		if ( $packageID == null ) {
			throw new MWException( wfMessage( 'pageexchange-packageidnull' ) );
		}

		$this->mInstalledExtensions = PXUtils::getInstalledExtensions( $this->getConfig() );
		$this->mInstalledPackages = PXUtils::getInstalledPackages( $user );
		$this->loadAllFiles();
		$package = null;
		foreach ( $this->mInstalledPackages as $installedPackage ) {
			if ( $installedPackage->getGlobalID() == $packageID ) {
				$package = $installedPackage;
				break;
			}
		}
		if ( $package == null ) {
			throw new MWException( wfMessage( 'pageexchange-packagenotexists', $packageID ) );
		}
		$package->update( $user );
	}

	private function loadAllFiles() {
		$pxFiles = [];
		$installedPackageIDs = [];
		foreach ( $this->mInstalledPackages as $installedPackage ) {
			$installedPackageIDs[] = $installedPackage->getGlobalID();
		}

		$packageFileURLs = $this->getConfig()->get( 'PageExchangePackageFiles' );
		foreach ( $packageFileURLs as $i => $url ) {
			$pxFiles[] = PXPackageFile::init( $url, -1, $i + 1, $this->mInstalledExtensions, $installedPackageIDs );
		}

		$fileDirectories = $this->getConfig()->get( 'PageExchangeFileDirectories' );
		foreach ( $fileDirectories as $dirNum => $fileDirectoryURL ) {
			$curPackageFiles = PXUtils::readFileDirectory( $fileDirectoryURL );
			foreach ( $curPackageFiles as $fileNum => $packageURL ) {
				$pxFiles[] = PXPackageFile::init( $packageURL, $dirNum + 1, $fileNum + 1, $this->mInstalledExtensions, $installedPackageIDs );
			}
		}

		foreach ( $pxFiles as $pxFile ) {
			$packages = $pxFile->getAllPackages( $this->getUser() );
			foreach ( $packages as $remotePackage ) {
				$this->loadRemotePackage( $remotePackage );
			}
		}
	}

	private function loadRemotePackage( $remotePackage ) {
		// Check if it matches an installed package.
		foreach ( $this->mInstalledPackages as &$installedPackage ) {
			if (
				$installedPackage->getGlobalID() !== null &&
				$installedPackage->getGlobalID() == $remotePackage->getGlobalID()
			) {
				$installedPackage->setAssociatedRemotePackage( $remotePackage );
				return;
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
		return 'Updating a package. Defined by https://www.mediawiki.org/wiki/Extension:Page_Exchange';
	}

	protected function getExamples() {
		return [
			'api.php?action=pxupdatepackage&packageid=com.wikiworks.books-demo-cargo'
		];
	}

}
