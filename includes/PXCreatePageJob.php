<?php
/**
 *
 * @file
 * @ingroup PX
 */

use MediaWiki\MediaWikiServices;

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

		$wikiPage = new WikiPage( $this->title );
		if ( !$wikiPage ) {
			$this->error = 'pageExchangeCreatePage: Wiki page not found "' . $this->title->getPrefixedDBkey() . '"';
			return false;
		}

		$pageText = PXUtils::getWebPageContents( $this->params['page_url'] );
		$contentType = $this->params['content_type'];
		if ( $contentType == 'JavaScript' ) {
			$newContent = new JavaScriptContent( $pageText );
		} elseif ( $contentType == 'CSS' ) {
			$newContent = new CSSContent( $pageText );
		} else {
			$newContent = new WikitextContent( $pageText );
		}
		$userID = $this->params['user_id'];
		if ( class_exists( 'MediaWiki\User\UserFactory' ) ) {
			// MW 1.35+
			$user = MediaWikiServices::getInstance()
				->getUserFactory()
				->newFromId( (int)$userID );
		} else {
			$user = User::newFromId( (int)$userID );
		}
		$editSummary = $this->params['edit_summary'];
		$flags = 0;

		if ( class_exists( 'PageUpdater' ) ) {
			// MW 1.32+
			$updater = $wikiPage->newPageUpdater( $user );
			$updater->setContent( MediaWiki\Revision\SlotRecord::MAIN, $newContent );
			$updater->saveRevision( CommentStoreComment::newUnsavedComment( $editSummary ), $flags );
		} else {
			$wikiPage->doEditContent( $newContent, $editSummary, $flags, false, $user );
		}

		// If this is a template, and Cargo is installed, tell Cargo
		// to automatically generate the table declared in this
		// template, if there is one.
		// @TODO - add a checkbox to the "install" page, to let the
		// user choose whether to create the table?
		if ( $this->title->getNamespace() == NS_TEMPLATE && class_exists( 'CargoDeclare' ) ) {
			CargoDeclare::$settings['createData'] = true;
			CargoDeclare::$settings['userID'] = $userID;
		}

		if ( !array_key_exists( 'file_url', $this->params ) ) {
			return true;
		}

		$fileURL = $this->params['file_url'];
		$this->createOrUpdateFile( $user, $editSummary, $fileURL );

		return true;
	}

	public function createOrUpdateFile( $user, $editSummary, $fileURL ) {
		// Code copied largely from /maintenance/importImages.php.
		$fileContents = PXUtils::getWebPageContents( $fileURL );
		$tempFile = tmpfile();
		fwrite( $tempFile, $fileContents );
		$tempFilePath = stream_get_meta_data( $tempFile )['uri'];
		$mwServices = MediaWikiServices::getInstance();
		if ( method_exists( $mwServices, 'getRepoGroup' ) ) {
			// MW 1.34+
			$file = $mwServices->getRepoGroup()->getLocalRepo()->newFile( $this->title );
		} else {
			$file = wfLocalFile( $this->title );
		}

		$mwProps = new MWFileProps( $mwServices->getMimeAnalyzer() );
		$props = $mwProps->getPropsFromPath( $tempFilePath, true );
		$flags = 0;
		$publishOptions = [];
		$handler = MediaHandler::getHandler( $props['mime'] );
		if ( $handler ) {
			$metadata = Wikimedia\quietCall( 'unserialize', $props['metadata'] );
			$publishOptions['headers'] = $handler->getContentHeaders( $metadata );
		} else {
			$publishOptions['headers'] = [];
		}
		$archive = $file->publish( $tempFilePath, $flags, $publishOptions );
		if ( is_callable( [ $file, 'recordUpload3' ] ) ) {
			// MW 1.35+
			$file->recordUpload3(
				$archive->value,
				$editSummary,
				$editSummary, // What does this get used for?
				$user,
				$props
			);
		} else {
			$file->recordUpload2(
				$archive->value,
				$editSummary,
				$editSummary, // What does this get used for?
				$props,
				$timestamp = false,
				$user
			);
		}
	}

}
