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
			return curl_exec( $ch );
		}

		if ( method_exists( AtEase::class, 'suppressWarnings' ) ) {
			// MW >= 1.33
			AtEase::suppressWarnings();
		} else {
			\MediaWiki\suppressWarnings();
		}
		$contents = file_get_contents( $url );
		if ( method_exists( AtEase::class, 'restoreWarnings' ) ) {
			// MW >= 1.33
			AtEase::restoreWarnings();
		} else {
			\MediaWiki\restoreWarnings();
		}

		return $contents;
	}
}
