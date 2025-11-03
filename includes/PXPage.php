<?php

/**
 * Class for a single page (and, possibly, an associated file) within a
 * package.
 *
 * @author Yaron Koren
 * @ingroup PX
 */

use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class PXPage {

	private $mName;
	private $mNamespace;
	private $mNamespaceConstant;
	private $mURL;
	private $mFileURL;
	private $mLocalTitle;
	private $mLocalTitleExists;
	private $mLink;
	private $mLocalLink;
	private $mSlots = [];

	public static function newFromData( $packagePageData, $baseURL ) {
		$page = new PXPage();
		$page->mName = $packagePageData->name;
		if ( property_exists( $packagePageData, 'namespace' ) ) {
			$page->mNamespaceConstant = $packagePageData->namespace;
		} else {
			// NS_MAIN is the default.
			$page->mNamespaceConstant = 'NS_MAIN';
		}
		if ( defined( $page->mNamespaceConstant ) ) {
			$page->mNamespace = constant( $page->mNamespaceConstant );
			$page->mLocalTitle = Title::makeTitleSafe( $page->mNamespace, $page->mName );
			if ( $page->mLocalTitle == null ) {
				return null;
			}
			$page->mLocalLink = Linker::link( $page->mLocalTitle );
			$page->mLocalTitleExists = $page->mLocalTitle->exists();
			$pageFullName = $page->mLocalTitle->getFullText();
			if ( $page->mNamespace == NS_FILE ) {
				if ( property_exists( $packagePageData, 'fileURL' ) ) {
					$page->mFileURL = $packagePageData->fileURL;
				} elseif ( property_exists( $packagePageData, 'fileURLPath' ) ) {
					$page->mFileURL = $baseURL . $packagePageData->fileURLPath;
				}
				if ( substr( $page->mFileURL, 0, 4 ) !== 'http' ) {
					return null;
				}
			}
		} else {
			// If the specified namespace is not defined on this
			// wiki, we'll just do the best we can.
			$page->mNamespace = $page->mNamespaceConstant;
			$page->mLocalTitleExists = false;
			$pageFullName = $page->mNamespace . ':' . $page->mName;
			$page->mLocalLink = $pageFullName;
		}
		if ( property_exists( $packagePageData, 'url' ) ) {
			$page->mURL = $packagePageData->url;
		} elseif ( property_exists( $packagePageData, 'urlPath' ) ) {
			$page->mURL = $baseURL . $packagePageData->urlPath;
		}
		if ( substr( $page->mURL, 0, 4 ) !== 'http' ) {
			return null;
		}
		if ( $page->mNamespace == NS_FILE ) {
			$page->mLink = Html::element( 'a', [ 'href' => $page->mFileURL ], $pageFullName ) . ' (' .
				Html::element( 'a', [ 'href' => $page->mURL ], wfMessage( 'pageexchange-textcontents' )->text() ) . ')';
		} else {
			$page->mLink = Html::element( 'a', [ 'href' => $page->mURL ], $pageFullName );
		}

		if ( property_exists( $packagePageData, 'slots' ) ) {
			foreach ( $packagePageData->slots as $slotName => $slot ) {
				if ( property_exists( $slot, 'url' ) ) {
					if ( substr( $slot->url, 0, 4 ) !== 'http' ) {
						continue;
					}
					$slotContentsURL = $slot->url;
				} elseif ( property_exists( $slot, 'urlPath' ) ) {
					$slotContentsURL = $baseURL . $slot->urlPath;
				} else {
					continue;
				}
				$page->mSlots[$slotName] = new stdClass();
				$page->mSlots[$slotName]->mURL = $slotContentsURL;
			}
		}

		return $page;
	}

	public function getName() {
		return $this->mName;
	}

	public function getNamespace() {
		return $this->mNamespace;
	}

	public function getLocalTitle() {
		return $this->mLocalTitle;
	}

	public function localTitleExists() {
		return $this->mLocalTitleExists;
	}

	public function getLink() {
		return $this->mLink;
	}

	public function getLocalLink() {
		return $this->mLocalLink;
	}

	public function getURL() {
		return $this->mURL;
	}

	public function setURL( $url ) {
		$this->mURL = $url;
	}

	public function getFileURL() {
		return $this->mFileURL;
	}

	public function getSlots() {
		return $this->mSlots;
	}

	public function isGadget() {
		// Ignore any pages named "Gadget-...", or "gadget-...",
		// in the MediaWiki namespace - these are presumably
		// meant to be handled by the Gadgets extension, and
		// thus have a separate loading mechanism.
		return $this->mNamespace == NS_MEDIAWIKI &&
			( substr( $this->mName, 0, 7 ) == 'Gadget-'
			|| substr( $this->mName, 0, 7 ) == 'gadget-'
			);
	}

	public function isJavaScript() {
		return $this->mNamespace == NS_MEDIAWIKI &&
			!$this->isGadget() &&
			substr( $this->mName, -3 ) == '.js';
	}

	public function isCSS() {
		return $this->mNamespace == NS_MEDIAWIKI &&
			!$this->isGadget() &&
			substr( $this->mName, -4 ) == '.css';
	}

	public function getRemoteContents() {
		return PXUtils::getWebPageContents( $this->mURL );
	}

	public function getLocalContents() {
		if ( !$this->mLocalTitleExists ) {
			return null;
		}

		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->mLocalTitle );
		$content = $wikiPage->getContent();
		if ( $content !== null ) {
			return $content->getText();
		} else {
			return null;
		}
	}

	/**
	 * Create or update a wiki page, and its associated file, if there is
	 * one.
	 */
	public function createOrUpdateWikiPage( $user, $packageName, $isInstall ) {
		// Most of the work - including creating the associated file,
		// if there is one - is actually done by a job.
		$editSummaryMsg = $isInstall ? 'pageexchange-installpackage' : 'pageexchange-updatepackage';
		$editSummary = wfMessage( $editSummaryMsg )->rawParams( $packageName )->inContentLanguage()->parse();

		$params = [
			'page_url' => $this->mURL,
			'user_id' => $user->getID(),
			'edit_summary' => $editSummary
		];
		if ( $this->mNamespace == NS_FILE ) {
			$params['file_url'] = $this->mFileURL;
		}
		if ( count( $this->mSlots ) > 0 ) {
			$params['slots'] = $this->mSlots;
		}
		$job = new PXCreatePageJob( $this->mLocalTitle, $params );
		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $job );
	}

	/**
	 * Delete a wiki page, and its associated file, if there is one.
	 */
	public function deleteWikiPage( $user, $packageName, $isUninstall ) {
		$mwServices = MediaWikiServices::getInstance();
		$wikiPage = $mwServices->getWikiPageFactory()->newFromTitle( $this->mLocalTitle );
		$editSummaryMsg = $isUninstall ? 'pageexchange-uninstallpackage' : 'pageexchange-updatepackage';
		$editSummary = wfMessage( $editSummaryMsg )->rawParams( $packageName )->inContentLanguage()->parse();
		$error = '';
		$wikiPage->doDeleteArticleReal( $editSummary, $user, false, null, $error );
		if ( $error != '' ) {
			throw new MWException( $error );
		}
		if ( $this->mNamespace == NS_FILE ) {
			$file = $mwServices->getRepoGroup()->getLocalRepo()->newFile( $this->mLocalTitle );
			$file->delete( $editSummary );
		}
	}

	public function getPageData() {
		$pageData = [
			'name' => $this->mName,
			'namespace' => $this->mNamespaceConstant,
			'url' => $this->mURL
		];
		if ( $this->mFileURL !== null ) {
			$pageData['fileURL'] = $this->mFileURL;
		}
		return $pageData;
	}
}
