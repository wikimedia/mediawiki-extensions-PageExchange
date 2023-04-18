<?php
/**
 *
 * @file
 * @ingroup PX
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\AtEase\AtEase;

/**
 * Background job to create a new page.
 *
 * @author Yaron Koren
 * @ingroup PX
 */
class PXCreatePageJob extends Job {

	function __construct( Title $title, array $params ) {
		parent::__construct( 'pageExchangeCreatePage', $title, $params );
		$this->removeDuplicates = true;
	}

	/**
	 * Run a pageExchangeCreatePage job
	 * @return bool success
	 */
	function run() {
		if ( $this->title === null ) {
			$this->error = "pageExchangeCreatePage: Invalid title";
			return false;
		}

		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->title );
		} else {
			$wikiPage = new WikiPage( $this->title );
		}
		if ( !$wikiPage ) {
			$this->error = 'pageExchangeCreatePage: Wiki page not found "' . $this->title->getPrefixedDBkey() . '"';
			return false;
		}

		$newPageText = PXUtils::getWebPageContents( $this->params['page_url'] );
		if ( array_key_exists( 'file_url', $this->params ) ) {
			$fileURL = $this->params['file_url'];
			$this->createOrUpdateFile( $user, $editSummary, $newPageText, $fileURL );
		}

		// If the new text is the same as the current text, we can exit now.
		// (We call the file stuff no matter what, because the file may be different.)
		$currentPageContent = $wikiPage->getContent( RevisionRecord::RAW );
		if ( $currentPageContent !== null ) {
			$currentPageText = $currentPageContent->getText();
			if ( trim( $newPageText ) == trim( $currentPageText ) ) {
				return false;
			}
		}

		// @todo - is all this necessary for pages where createOrUpdateFile()
		// was already called?
		$newContent = ContentHandler::makeContent( $newPageText, $this->title );
		$userID = $this->params['user_id'];
		$user = MediaWikiServices::getInstance()
			->getUserFactory()
			->newFromId( (int)$userID );
		$editSummary = $this->params['edit_summary'];
		$flags = 0;

		$updater = $wikiPage->newPageUpdater( $user );
		$updater->setContent( MediaWiki\Revision\SlotRecord::MAIN, $newContent );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( $editSummary ), $flags );

		// If this is a template, and Cargo is installed, tell Cargo
		// to automatically generate the table declared in this
		// template, if there is one.
		// @TODO - add a checkbox to the "install" page, to let the
		// user choose whether to create the table?
		if ( $this->title->getNamespace() == NS_TEMPLATE && class_exists( 'CargoDeclare' ) ) {
			CargoDeclare::$settings['createData'] = true;
			CargoDeclare::$settings['userID'] = $userID;
		}

		return true;
	}

	public function createOrUpdateFile( $user, $editSummary, $newPageText, $fileURL ) {
		// Code copied largely from /maintenance/importImages.php.
		$fileContents = PXUtils::getWebPageContents( $fileURL );
		$tempFile = tmpfile();
		fwrite( $tempFile, $fileContents );
		$tempFilePath = stream_get_meta_data( $tempFile )['uri'];
		$mwServices = MediaWikiServices::getInstance();
		$file = $mwServices->getRepoGroup()->getLocalRepo()->newFile( $this->title );

		$mwProps = new MWFileProps( $mwServices->getMimeAnalyzer() );
		$props = $mwProps->getPropsFromPath( $tempFilePath, true );
		$flags = 0;
		$publishOptions = [];
		$handler = MediaHandler::getHandler( $props['mime'] );
		if ( $handler ) {
			$metadata = AtEase::quietCall( 'unserialize', $props['metadata'] );
			$publishOptions['headers'] = $handler->getContentHeaders( $metadata );
		} else {
			$publishOptions['headers'] = [];
		}
		$archive = $file->publish( $tempFilePath, $flags, $publishOptions );
		$file->recordUpload3(
			$archive->value,
			$editSummary,
			$newPageText,
			$user,
			$props
		);
	}

}
