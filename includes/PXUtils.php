<?php
use Wikimedia\AtEase\AtEase;

class PXUtils {
	public static function getInstalledExtensions( $config ) {
		$installedExtensions = [];
		// Extensions loaded via wfLoadExtension().
		$registeredExtensions = ExtensionRegistry::getInstance()->getAllThings();
		foreach ( $registeredExtensions as $extName => $extData ) {
			// Make the names "space-insensitive".
			$extensionName = str_replace( ' ', '', $extName );
			$installedExtensions[] = $extensionName;
		}

		// For MW 1.35+, this only gets extensions that are loaded the
		// old way, via include_once() or require_once().
		$extensionCredits = $config->get( 'ExtensionCredits' );
		foreach ( $extensionCredits as $group => $exts ) {
			foreach ( $exts as $ext ) {
				// Make the names "space-insensitive".
				$extensionName = str_replace( ' ', '', $ext['name'] );
				$installedExtensions[] = $extensionName;
			}
		}
		return $installedExtensions;
	}

	public static function getInstalledPackages( $user ) {
		$installedPackages = [];
		$dbr = wfGetDb( DB_REPLICA );
		$res = $dbr->select(
			'px_packages',
			[ 'pxp_id', 'pxp_name', 'pxp_package_data' ]
		);
		while ( $row = $res->fetchRow() ) {
			$installedPackages[] = PXInstalledPackage::newFromDB( $row, $user );
		}
		return $installedPackages;
	}

	public static function getWebPageContents( $url ) {
		// Use cURL, if it's installed - it seems to have a better
		// chance of working.
		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_USERAGENT, 'request' );
			$contents = curl_exec( $ch );
			$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			if ( $httpCode !== 200 ) {
				// @todo - return/throw $contents['message']?
				// It may contain useful information.
				return '';
			}
			return $contents;
		}

		AtEase::suppressWarnings();
		$contents = file_get_contents( $url );
		AtEase::restoreWarnings();

		return $contents;
	}

	public static function readFileDirectory( $fileDirectoryURL ) {
		$packageFiles = [];
		$fileDirectoryContents = self::getWebPageContents( $fileDirectoryURL );
		$fileDirectoryLines = explode( "\n", $fileDirectoryContents );
		foreach ( $fileDirectoryLines as $fileDirectoryLine ) {
			// Allow blank lines, and comments.
			if ( $fileDirectoryLine == '' ) {
				continue;
			}
			$firstChar = $fileDirectoryLine[0];
			if ( in_array( $firstChar, [ ';', '#', '/' ] ) ) {
				continue;
			}
			$packageFiles[] = $fileDirectoryLine;
		}
		return $packageFiles;
	}
}
