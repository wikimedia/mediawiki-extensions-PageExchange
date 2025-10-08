<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/*
 * Mostly copied from the files PF_Utils.php and PF_ValuesUtils.php in the Page Forms extension
*/

class PXExportPackage extends PXPackage {

	/**
	 * @var array
	 */
	private static $mNamespaceConstants = [];

	protected ?string $mGithubRepo = null;

	function __construct(
		?string $packageName = null,
		string $packageDesc = '',
		?string $githubRepo = null,
		?string $url = null,
		?string $version = '',
		?string $author = '',
		?string $publisher = '',
		?array $dependencies = null,
		?array $extensions = null
	) {
		global $wgLanguageCode;

		$this->mName = $packageName ?? time();
		$this->mDescription = $packageDesc;
		$this->mURL = ( $githubRepo ? "https://github.com/$githubRepo" : $url );
		$this->mGithubRepo = $githubRepo;
		$this->mVersion = $version;
		$this->mAuthor = $author;
		$this->mPublisher = $publisher;
		$this->mRequiredPackages = $dependencies;
		$this->mRequiredExtensions = $extensions;
		$this->mLanguage = $wgLanguageCode;

		$this->mGlobalID = str_replace( ' ', '.', $this->mName );
	}

	public function getAllPages(): array {
		$pages = [];
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$res = $dbr->select( 'page', [ 'page_title', 'page_namespace' ] );
		if ( $res ) {
			foreach ( $res as $row ) {
				$cur_title = Title::makeTitleSafe( $row['page_namespace'], $row['page_title'] );
				if ( $cur_title === null ) {
					continue;
				}
				$pages[] = $cur_title->getPrefixedText();
			}
		}
		return $pages;
	}

	/**
	 * @param string $root output directory path
	 *
	 * @param bool $save save to file
	 *
	 * @return array|bool
	 * @throws MWException
	 * @throws Exception
	 */
	public function exportToDirectory( string $root, bool $save = true ) {
		if ( $save && ( !is_dir( $root ) || !is_writable( $root ) ) ) {
			throw new Exception( 'Output directory does not exist or you have no write permissions' );
		}
		$contents = [];
		foreach ( $this->mPages as $page ) {
			$title = Title::newFromText( $page );
			$filename = $title->getText();
			$namespace = $title->getNamespace();
			$namespaceName = $this->getNamespaceName( $namespace );
			if ( !$namespaceName || $namespaceName == '' ) {
				continue;
			}
			if ( strpos( $namespaceName, '/' ) !== false ) {
				$namespaceName = str_replace( '/', '#', $namespaceName );
			}
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
			$content = $wikiPageFactory->newFromTitle( $title )->getContent()->getWikitextForTransclusion();
			if ( $save && !file_exists( $root . '/' . $namespaceName ) ) {
				mkdir( $root . '/' . $namespaceName );
			}
			if ( strpos( $filename, '/' ) !== false ) {
				$filename = str_replace( '/', '#', $filename );
			}
			$targetFileName = $root . '/' . $namespaceName . '/' . $filename . '.mediawiki';
			if ( $save ) {
				file_put_contents( $targetFileName, $content );
			} else {
				$contents[$targetFileName] = $content;
			}
		}
		if ( !$save ) {
			return $contents;
		}
		return true;
	}

	/**
	 * Returns namespace canonical name
	 *
	 * @param int $namespace
	 *
	 * @return string
	 */
	public function getNamespaceName( int $namespace ): string {
		if ( $namespace === NS_MAIN ) {
			return 'Main';
		}
		return MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalName( $namespace );
	}

	/**
	 * Returns namespace constant name (NS_MAIN, NS_FILE, etc) by constant value
	 *
	 * @param string $value
	 *
	 * @return array|mixed|null
	 */
	public function getNamespaceByValue( $value ) {
		if ( self::$mNamespaceConstants ) {
			return self::$mNamespaceConstants[$value] ?? $value;
		}
		$defines = get_defined_constants( true );
		$constants = array_filter(
			$defines['user'],
			static function ( $k ) {
				return substr( $k, 0, 3 ) === 'NS_';
			},
			ARRAY_FILTER_USE_KEY
		);
		$constants = array_flip( $constants );
		self::$mNamespaceConstants = $constants;
		return $constants[$value] ?? $value;
	}

	/**
	 * @param string $root Output directory
	 * @param string|null $directoryStructure Flag to output directory structure
	 * @param bool $save to save resulting JSON
	 *
	 * @return array|bool
	 */
	public function exportJSON( string $root, ?string $directoryStructure = null, bool $save = true ) {
		$filename = $root;
		if ( substr( $filename, -5 ) !== '.json' ) {
			// Default to 'page-exchange.json'
			$filename .= '/page-exchange.json';
		}

		$json = [
			'publisher' => $this->mPublisher,
			'author' => $this->mAuthor,
			'language' => $this->mLanguage,
			"url" => $this->mURL,
			"packages" => [
				$this->mName => [
					"globalID" => str_replace( ' ', '.', $this->mName ),
					"description" => $this->mDescription,
					"version" => $this->mVersion,
					"pages" => [],
					"requiredExtensions" => $this->mRequiredExtensions ?? [],
					"requiredPackages" => $this->mRequiredPackages ?? [],
				]
			]
		];
		$jsonPages = [];
		$repo = $this->mGithubRepo;
		foreach ( $this->mPages as $page ) {
			$title = Title::newFromText( $page );
			$name = $title->getText();
			$escapedName = str_replace( '/', '|', $name );
			$namespace = $this->getNamespaceByValue( $title->getNamespace() );
			$item = [
				"name" => $name,
				"namespace" => $namespace,
				"url" => $title->getFullURL( 'action=raw' )
			];
			if ( $repo !== null ) {
				$item['url'] =
					"https://raw.githubusercontent.com/{$repo}/master/" .
					rawurlencode(
						"{$this->getNamespaceName( $title->getNamespace() )}"
						. "/" . "{$escapedName}.mediawiki"
					);
			}
			$jsonPages[] = $item;
		}
		$json['packages'][$this->mName]['pages'] = $jsonPages;

		if ( $directoryStructure ) {
			unset( $json['packages'][$this->mName]['pages'] );
			$repo = explode( '/', $repo );
			$repoName = $repo[1] ?? '';
			$repoAccount = $repo[0] ?? '';

			$json['packages'][$this->mName]['directoryStructure'] = [
				'service' => 'GitHub',
				'accountName' => $repoAccount,
				'repositoryName' => $repoName,
			];

			foreach ( $this->mPages as $page ) {
				$title = Title::newFromText( $page );
				$namespace = $this->getNamespaceByValue( $title->getNamespace() );
				$filenamePrefix = preg_replace( '/\W/', '_', $namespace );
				$fileNamePrefix = str_replace( 'NS_', '', $filenamePrefix . '/' );
				$fileNameSuffix = '.mediawiki';
				$setting = [
					'namespace' => $namespace,
					'fileNamePrefix' => $fileNamePrefix,
					'fileNameSuffix' => $fileNameSuffix,
				];
				if ( $namespace === "NS_FILE" ) {
					// store forward slash with escape character
					$setting['actualFileNamePrefix'] = 'File/';
					$setting['actualFileNameSuffix'] = '';
				}
				$json['packages'][$this->mName]['directoryStructure']['namespaceSettings'][] = $setting;
			}
		}

		if ( !$save ) {
			return [ $filename, json_encode( $json, JSON_PRETTY_PRINT ) ];
		}
		file_put_contents( $filename, json_encode( $json, JSON_PRETTY_PRINT ) );
		return true;
	}

	public function addCategory( string $category, int $levels = 1, ?string $substring = null, bool $inclusive = true ): bool {
		// TODO: test with displaytitle overrides!!
		$pages = $this->getAllPagesForCategory( $category, $levels, $substring, $inclusive );
		$this->addPages( $pages );
		return true;
	}

	public function getPages(): array {
		return $this->mPages;
	}

	public function addPages( array $pages ) {
		$this->mPages = array_merge( $this->mPages ?? [], $pages );
	}

	public function addAllPages(): void {
		$pages = $this->getAllPages();
		# TODO I guess we make Title objects, turn them into plain text, then make Titles again.
		$this->mPages = $pages;
	}

	public function clearPages(): void {
		$this->mPages = [];
	}

	/**
	 * Get all the pages that belong to a category and all its
	 * subcategories, down a certain number of levels - heavily based on
	 * SMW's SMWInlineQuery::includeSubcategories().
	 *
	 * @param string $top_category
	 * @param int $num_levels
	 * @param string|null $substring
	 * @param bool $inclusive
	 *
	 * @return string[]|string
	 */
	public function getAllPagesForCategory( $top_category, $num_levels, $substring = null, $inclusive = false ) {
		if ( $num_levels == 0 ) {
			return $top_category;
		}
		global $wgPageFormsMaxAutocompleteValues;

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		// true for MW 1.45+
		$useTargetID = !$dbr->fieldExists( 'categorylinks', 'cl_to' );

		$top_category = str_replace( ' ', '_', $top_category );
		$categories = [ $top_category ];
		$checkcategories = [ $top_category ];
		$pages = [];
		$sortkeys = [];
		for ( $level = $num_levels; $level > 0; $level-- ) {
			$newcategories = [];
			foreach ( $checkcategories as $category ) {
				$tables = [ 'categorylinks', 'page' ];
				if ( $useTargetID ) {
					$tables[] = 'linktarget';
				}
				$columns = [ 'page_title', 'page_namespace' ];
				$conditions = [];
				if ( $useTargetID ) {
					$conditions['lt_title'] = $category;
				} else {
					$conditions['cl_to'] = $category;
				}

				$join = [];
				$join['categorylinks'] = [ 'JOIN', 'page_id = cl_from' ];
				if ( $useTargetID ) {
					$join['linktarget'] = [ 'JOIN', 'cl_target_id = lt_id' ];
				}

				if ( $substring != null ) {
					$conditions[] = $this->getSQLConditionForAutocompleteInColumn(
							'page_title',
							$substring
						) . ' OR page_namespace = ' . NS_CATEGORY;
				}

				$res = $dbr->select(
					$tables,
					$columns,
					$conditions,
					__METHOD__,
					$options = [
						'ORDER BY' => 'cl_type, cl_sortkey',
						'LIMIT' => $wgPageFormsMaxAutocompleteValues
					],
					$join
				);
				if ( $res ) {
					// @codingStandardsIgnoreStart
					while ( $res && $row = $res->fetchRow() ) {
						// @codingStandardsIgnoreEnd
						if ( !array_key_exists( 'page_title', $row ) ) {
							continue;
						}
						$page_namespace = $row['page_namespace'];
						$page_name = $row['page_title'];
						if ( $page_namespace == NS_CATEGORY ) {
							if ( !in_array( $page_name, $categories ) ) {
								$newcategories[] = $page_name;
							}
						} else {
							$cur_title = Title::makeTitleSafe( $page_namespace, $page_name );
							if ( $cur_title === null ) {
								// This can happen if it's
								// a "phantom" page, in a
								// namespace that no longer exists.
								continue;
							}
							$cur_value = $cur_title->getPrefixedText();
							if ( !in_array( $cur_value, $pages ) ) {
								if ( array_key_exists( 'pp_displaytitle_value', $row )
									 && ( $row['pp_displaytitle_value'] ) !== null
									 && trim( str_replace( '&#160;', '',
										strip_tags( $row['pp_displaytitle_value'] ) ) ) !== ''
								) {
									$pages[$cur_value . '@'] = htmlspecialchars_decode( $row['pp_displaytitle_value'] );
								} else {
									$pages[$cur_value . '@'] = $cur_value;
								}
								if ( array_key_exists( 'pp_defaultsort_value', $row ) &&
									 ( $row['pp_defaultsort_value'] ) !== null ) {
									$sortkeys[$cur_value] = $row['pp_defaultsort_value'];
								} else {
									$sortkeys[$cur_value] = $cur_value;
								}
							}
						}
					}
					$res->free();
				}
			}
			if ( count( $newcategories ) == 0 ) {
				return $this->fixedMultiSort( $sortkeys, $pages );
			} else {
				$categories = array_merge( $categories, $newcategories );
				if ( $inclusive ) {
					foreach ( $newcategories as $newcategory ) {
						$pages[ 'Category:' . $newcategory . '@' ] = 'Category:' . $newcategory;
						$sortkeys[ 'Category:' . $newcategory ] = 'Category:' . $newcategory;
					}
				}
			}
			$checkcategories = array_diff( $newcategories, [] );
		}
		return $this->fixedMultiSort( $sortkeys, $pages );
	}

	/**
	 * Returns a SQL condition for autocompletion substring value in a column.
	 *
	 * @param string $column Value column name
	 * @param string $substring Substring to look for
	 * @param bool $replaceSpaces
	 *
	 * @return string SQL condition for use in WHERE clause
	 */
	private function getSQLConditionForAutocompleteInColumn( $column, $substring, $replaceSpaces = true ): string {
		global $wgDBtype, $wgPageFormsAutocompleteOnAllChars;

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		// CONVERT() is also supported in PostgreSQL, but it doesn't
		// seem to work the same way.
		if ( $wgDBtype == 'mysql' ) {
			$column_value = "LOWER(CONVERT($column USING utf8))";
		} else {
			$column_value = "LOWER($column)";
		}

		$substring = strtolower( $substring );
		if ( $replaceSpaces ) {
			$substring = str_replace( ' ', '_', $substring );
		}

		if ( $wgPageFormsAutocompleteOnAllChars ) {
			return $column_value . $dbr->buildLike( $dbr->anyString(), $substring, $dbr->anyString() );
		} else {
			$spaceRepresentation = $replaceSpaces ? '_' : ' ';
			return $column_value . $dbr->buildLike( $substring, $dbr->anyString() ) . ' OR ' . $column_value .
				   $dbr->buildLike(
					   $dbr->anyString(),
					   $spaceRepresentation . $substring,
					   $dbr->anyString()
				   );
		}
	}

	/**
	 * array_multisort() unfortunately messes up array keys that are
	 * numeric - they get converted to 0, 1, etc. There are a few ways to
	 * get around this, but I (Yaron) couldn't get those working, so
	 * instead we're going with this hack, where all key values get
	 * appended with a '@' before sorting, which is then removed after
	 * sorting. It's inefficient, but it's probably good enough.
	 *
	 * @param string[] $sortkeys
	 * @param string[] $pages
	 *
	 * @return string[] a sorted version of $pages, sorted via $sortkeys
	 */
	private function fixedMultiSort( $sortkeys, $pages ): array {
		array_multisort( $sortkeys, $pages );
		$newPages = [];
		foreach ( $pages as $key => $value ) {
			$fixedKey = rtrim( $key, '@' );
			$newPages[$fixedKey] = $value;
		}
		return $newPages;
	}

	/**
	 *  Fill out the abstract functions that we don't need
	 */
	public function processPages() {
		throw new Exception( 'Call export() or exportJSON() instead' );
	}

	public function getFullHTML() {
		throw new Exception( 'Call export() or exportJSON() instead' );
	}

}
