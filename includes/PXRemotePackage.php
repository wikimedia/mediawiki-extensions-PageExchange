<?php
/**
 * Class for a "remote" (uninstalled) package.
 *
 * @author Yaron Koren
 * @ingroup PX
 */

class PXRemotePackage extends PXPackage {

	private $mFileNum;
	private $mMissingExtensions = [];
	private $mMissingPackages = [];
	private $mUninstallableReasons = [];
	private $mIsMatch = true;

	public static function newFromData( $fileNum, $fileData, $packageName, $packageData, $installedExtensions, $installedPackages ) {
		$package = new PXRemotePackage();

		$package->mName = $packageName;
		$package->mFileNum = $fileNum;
		$package->populateWithData( $fileData, $packageData );
		$package->checkApplicabilityToSite( $installedExtensions, $installedPackages );

		return $package;
	}

	public function processPages() {
		$matchingPageFound = false;
		$nonMatchingPageFound = false;
		$pageLinks = [];
		if ( count( $this->mPages ) == 0 ) {
			$this->mUninstallableReasons[] = 'no-pages';
		}
		foreach ( $this->mPages as $page ) {
			if ( $page == null ) {
				continue;
			}
			if ( $page->getLocalTitle() == null && !in_array( 'bad-namespace', $this->mUninstallableReasons ) ) {
				$this->mUninstallableReasons[] = 'bad-namespace';
			}
			$pageLink = $page->getLink();
			if ( $page->localTitleExists() ) {
				$pageLink = "<em>" . $pageLink . "</em>";
				//$localLink = Linker::link( $page->getLocalTitle(), 'local', [], [ 'action' => 'raw' ] );
				//$pageLink .= " ($localLink)";
				$matchingPageFound = true;
			} else {
				$nonMatchingPageFound = true;
			}
			$pageLinks[] = $pageLink;
		}
		if ( count( $pageLinks ) > 7 ) {
			$shownLinks = array_splice( $pageLinks, 0, 7 );
			$shownLinksStr = implode( ', ', $shownLinks );
			$hiddenLinksStr = implode( ', ', $pageLinks );
			$this->mPagesString =<<<END
<span class="pageExchangePageLinks">
$shownLinksStr, <span class="pageExchangeAdditionalPages">$hiddenLinksStr</span>
(<a class="pageExchangeToggle">show more</a>)
</span>

END;
		} else {
			$this->mPagesString = implode( ', ', $pageLinks );
		}
		if ( $matchingPageFound ) {
			if ( $nonMatchingPageFound ) {
				$this->mPagesStatus = 'partial';
			} else {
				$this->mPagesStatus = 'complete';
			}
		} else {
			$this->mPagesStatus = 'none';
		}
	}

	public function isMatch() {
		return $this->mIsMatch;
	}

	public function isUninstallable() {
		return count( $this->mUninstallableReasons ) > 0;
	}

	public function getCardBGColor() {
		if ( $this->mIsMatch ) {
			if ( $this->mPagesStatus == 'complete' ) {
				// Possibly installed already
				return [ 254, 246, 231 ];
			} elseif ( $this->mPagesStatus == 'partial' ) {
				return [ 255, 200, 200 ];
			} else {
				return [ 255, 255, 255 ];
			}
		} else {
			return [ 248, 249, 250 ];
		}
	}

	public function getCardHeaderBGColor() {
		if ( $this->mIsMatch ) {
			if ( $this->mPagesStatus == 'complete' ) {
				// Possibly installed already
				return '#fc3';
			} elseif ( $this->mPagesStatus == 'partial' ) {
				return '#d33';
			} else {
				return '#c8ccd1';
			}
		} else {
			return '#a2a9b1';
		}
	}

	public function getCardBodyHTML() {
		global $wgLanguageCode;

		$packageHTML = '';

		if ( $this->mPagesStatus == 'partial' ) {
			$packageHTML .= $this->displayWarningMessage( 'Downloading this package would overwrite some pages on your wiki.' );
		} elseif ( $this->mPagesStatus == 'complete' ) {
			$packageHTML .= $this->displayWarningMessage( 'Every page in this package is already found on your wiki; perhaps you have already unofficially installed it.' );
		}
		if ( $this->isUninstallable() ) {
			$errorMsg = wfMessage( 'pageexchange-uninstallablepackage' )->parse();
			$errorMsg .= "\n<ul>";
			foreach ( $this->mUninstallableReasons as $reason ) {
				if ( $reason == 'no-pages' ) {
					$errorMsg .= '<li>No pages are defined</li>';
				} elseif ( $reason == 'no-identifier' ) {
					$errorMsg .= '<li>Lacks a unique global ID</li>';
				} elseif ( $reason == 'bad-namespace' ) {
					$errorMsg .= '<li>It uses a namespace not defined on your wiki</li>';
				}
			}
			$errorMsg .= '</ul>';
			$packageHTML .= $this->displayWarningMessage( $errorMsg );
		} elseif ( !$this->mIsMatch ) {
			$packageHTML .= $this->displayWarningMessage( wfMessage( 'pageexchange-inappropriatepackage' )->parse() );
		}

		$packageHTML .= $this->displayDescription();
		$packageHTML .= $this->displayWebsite();
		$publisher = $this->mPublisher;
		if ( $this->mPublisherURL !== null ) {
			$publisher = Html::element( 'a', [ 'href' => $this->mPublisherURL ], $publisher );
		}
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-publisher', $publisher );
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-author', $this->mAuthor );

		$packageHTML .= $this->displayAttribute( 'pageexchange-package-pages', $this->mPagesString );
		$hasMismatchedLanguage = ( $this->mLanguage != '' ) && ( $this->mLanguage != $wgLanguageCode );
		$languageString = $hasMismatchedLanguage ? "<span class=\"error\">{$this->mLanguage}</span>" : $this->mLanguage;
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-language', $languageString, $hasMismatchedLanguage );
		$hasMismatchedExtensions = count( $this->mMissingExtensions ) > 0;
		$packageRequiredExtensionsString = $this->getRequiredExtensionsString();
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-requiredextensions', $packageRequiredExtensionsString, $hasMismatchedExtensions );
		$hasMismatchedPackages = count( $this->mMissingPackages ) > 0;
		$packageRequiredPackagesString = $this->getRequiredPackagesString();
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-requiredpackages', $packageRequiredPackagesString, $hasMismatchedPackages );

		if ( count( $this->mUninstallableReasons ) == 0 ) {
			$packageLink = $this->getPackageLink(
				wfMessage( 'pageexchange-package-install' )->parse(),
				[ 'name' => $this->mName, 'fileNum' => $this->mFileNum ]
			);
			$packageHTML .= Html::rawElement( 'p', [ 'class' => 'pageExchangeCardBottomLink' ], $packageLink );
		}

		return $packageHTML;
	}

	public function getRequiredExtensionsString() {
		if ( $this->mRequiredExtensions == null ) {
			return '';
		}

		$text = '';
		foreach ( $this->mRequiredExtensions as $i => $requiredExt ) {
			if ( $i > 0 ) {
				$text .= ', ';
			}
			if ( in_array( $requiredExt, $this->mMissingExtensions ) ) {
				$text .= "<span class=\"error\">$requiredExt</span>";
			} else {
				$text .= $requiredExt;
			}
		}
		return $text;
	}

	public function getRequiredPackagesString() {
		if ( $this->mRequiredPackages == null ) {
			return '';
		}

		$text = '';
		foreach ( $this->mRequiredPackages as $i => $requiredPackage ) {
			if ( $i > 0 ) {
				$text .= ', ';
			}
			if ( in_array( $requiredPackage, $this->mMissingPackages ) ) {
				$text .= "<span class=\"error\">$requiredPackage</span>";
			} else {
				$text .= $requiredPackage;
			}
		}
		return $text;
	}

	private function checkApplicabilityToSite( $installedExtensions, $installedPackages ) {
		global $wgLanguageCode;

		if ( $this->mGlobalID == null ) {
			$this->mUninstallableReasons[] = 'no-identifier';
		}

		if ( $this->mRequiredExtensions != null ) {
			foreach ( $this->mRequiredExtensions as $requiredExt ) {
				if ( !in_array( $requiredExt, $installedExtensions ) ) {
					$this->mMissingExtensions[] = $requiredExt;
				}
			}
		}

		if ( $this->mRequiredPackages != null ) {
			foreach ( $this->mRequiredPackages as $requiredPackage ) {
				if ( !in_array( $requiredPackage, $installedPackages ) ) {
					$this->mMissingPackages[] = $requiredPackage;
				}
			}
		}

		if ( count( $this->mMissingExtensions ) > 0 || count( $this->mMissingPackages ) > 0 ) {
			$this->mIsMatch = false;
			return;
		}

		if ( $this->mLanguage != '' && $this->mLanguage !== $wgLanguageCode ) {
			$this->mIsMatch = false;
			return;
		}
	}

	public function getFullHTML() {
		$packageHTML = '';
		$packageHTML .= $this->displayDescription();
		$packageHTML .= $this->displayWebsite( true );
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-publisher', $this->mPublisher );
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-author', $this->mAuthor );
		$pagesString = "<ul>\n";
		foreach ( $this->mPages as $page ) {
			$pagesString .= "<li>" . $page->getLink();
			$remoteContents = $page->getRemoteContents();
			if ( $remoteContents == null ) {
				$pagesString .= ' - <span class="error">No contents found at this page!</span>';
			} elseif ( ! $page->localTitleExists() ) {
				// Do nothing.
			} else {
				$localContents = $page->getLocalContents();
				if ( $localContents == $remoteContents ) {
					$pagesString .= ' - there is a local copy of this page that matches the external version.';
				} else {
					$pagesString .= ' - there is a local copy of this page that differs from the external version.';
				}
			}
			if ( $page->getNamespace() == NS_FILE ) {
				$pagesString .= ' (It is unknown whether the local copy of the file itself matches the external version.)';
			}
		}
		$pagesString .= "</ul>\n";
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-pages', $pagesString );
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-language', $this->mLanguage );
		$packageRequiredExtensionsString = $this->getRequiredExtensionsString();
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-requiredextensions', $packageRequiredExtensionsString );
		$packageRequiredPackagesString = $this->getRequiredPackagesString();
		$packageHTML .= $this->displayAttribute( 'pageexchange-package-requiredpackages', $packageRequiredPackagesString );

		$installButton = new OOUI\ButtonInputWidget( [
			'label' => wfMessage( 'pageexchange-package-install' )->parse(),
			'useInputTag' => true,
			'type' => 'submit',
			'name' => 'install',
			'flags' => 'progressive'
		] );

		$packageHTML .=<<<END
<form method="post">
<div style="border-top: 1px #ccc solid; margin-top: 20px; padding: 15px;">
$installButton
</div>
</form>

END;

		return $packageHTML;
	}

	public function getPackageData() {
		$pagesData = [];
		foreach ( $this->mPages as $page ) {
			$pagesData[] = $page->getPageData();
		}
		$packageData = [
			'globalID' => $this->mGlobalID,
			'version' => $this->mVersion,
			'description' => $this->mDescription,
			'publisher' => $this->mPublisher,
			'publisherURL' => $this->mPublisherURL,
			'author' => $this->mAuthor,
			'language' => $this->mLanguage,
			'url' => $this->mURL,
			'requiredExtensions' => $this->mRequiredExtensions,
			'requiredPackages' => $this->mRequiredPackages,
			'pages' => $pagesData
		];

		return $packageData;
	}

	public function install( $user ) {
		$dbr = wfGetDB( DB_MASTER );

		$maxPackageID = $dbr->selectField( 'px_packages', 'MAX(pxp_id)' );
		$packageID = $maxPackageID + 1;
		$installValues = [
			'pxp_id' => $packageID,
			'pxp_date_installed' => 'NOW()',
			'pxp_installing_user' => $user->getID(),
			'pxp_name' => $this->mName,
			'pxp_global_id' => $this->mGlobalID,
			'pxp_package_data' => json_encode( $this->getPackageData() )
		];
		$dbr->insert( 'px_packages', $installValues );

		foreach ( $this->mPages as $page ) {
			$page->createOrUpdateWikiPage( $user, $this->mName, true );
		}

		$this->logAction( 'installpackage', $user );
	}

}
