<?php

class PXHooks {

	public static function describeDBSchema( DatabaseUpdater $updater ) {
		// DB updates.
		// For now, there's just a single SQL file for all DB types.
		$updater->addExtensionTable( 'px_packages', __DIR__ . "/../PageExchange.sql" );

		return true;
	}

	/**
	 * Implements AdminLinks hook from the Admin Links extension.
	 *
	 * @param ALTree &$adminLinksTree
	 * @return bool
	 */
	public static function addToAdminLinks( ALTree &$adminLinksTree ) {
		$generalSection = $adminLinksTree->getSection( wfMessage( 'adminlinks_general' )->text() );
		$extensionsRow = $generalSection->getRow( 'extensions' );

		if ( $extensionsRow === null ) {
			$extensionsRow = new ALRow( 'extensions' );
			$generalSection->addRow( $extensionsRow );
		}

		$extensionsRow->addItem( ALItem::newFromSpecialPage( 'Packages' ) );

		return true;
	}

}
