<?php

/**
 * Class for a JSON file holding the definitions of one or more packages.
 *
 * @author Yaron Koren
 * @ingroup PX
 */
class PXPackageFile {

	private $mURL;
	private $mData;
	private $mDirectoryNum;
	private $mFileNum;
	private $mInstalledExtensions;
	private $mInstalledPackages;

	public static function init( $url, $directoryNum, $fileNum, $installedExtensions, $installedPackages ) {
		$json = PXUtils::getWebPageContents( $url );
		if ( $json === null || $json === '' ) {
			throw new MWException( 'Error: no file found at ' . $url );
		}
		$fileData = json_decode( $json );
		if ( $fileData === null ) {
			throw new MWException( 'Error: the file at ' . $url . ' contains invalid JSON.' );
		}

		$packageFile = new PXPackageFile();
		$packageFile->mURL = $url;
		$packageFile->mData = $fileData;
		$packageFile->mDirectoryNum = $directoryNum;
		$packageFile->mFileNum = $fileNum;
		$packageFile->mInstalledExtensions = $installedExtensions;
		$packageFile->mInstalledPackages = $installedPackages;

		return $packageFile;
	}

	public function getPackage( $packageName, $user ) {
		if ( !property_exists( $this->mData, 'packages' ) ) {
			throw new MWException( 'Error: no packages found in file' );
		}
		if ( !property_exists( $this->mData, 'packages' ) ) {
			throw new MWException( "Error: no package found named $packageName" );
		}
		$packageData = $this->mData->packages->$packageName;
		return PXRemotePackage::newFromData(
			$this->mDirectoryNum,
			$this->mFileNum,
			$this->mData,
			$packageName,
			$packageData,
			$this->mInstalledExtensions,
			$this->mInstalledPackages,
			$user
		);
	}

	public function getAllPackages( $user ) {
		if ( !property_exists( $this->mData, 'packages' ) ) {
			throw new MWException( 'Error: no packages found in file' );
		}
		$packages = [];
		foreach ( $this->mData->packages as $name => $packageData ) {
			$packages[] = PXRemotePackage::newFromData(
				$this->mDirectoryNum,
				$this->mFileNum,
				$this->mData,
				$name,
				$packageData,
				$this->mInstalledExtensions,
				$this->mInstalledPackages,
				$user
			);
		}
		return $packages;
	}

	function getURL() {
		return $this->mURL;
	}

}
