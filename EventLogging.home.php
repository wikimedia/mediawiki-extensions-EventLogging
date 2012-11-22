<?php
/**
 * Hooks for EventLogging extension.
 *
 * These run on the wiki which hosts the event data models article.
 *
 * @file
 * @ingroup Extensions
 */

class EventLoggingHomeHooks {

	const CACHE_KEY = 'ext.eventLogging:dataModels';
	const TITLE_TEXT = 'EventDataModels.json';


	/**
	 * Check if a given Title object refers to the data models page.
	 *
	 * @param $title Title
	 * @return bool
	 */
	public static function isModelsTitle( $title ) {
		global $wgDBname, $wgEventLoggingDBname;

		return $wgDBname === $wgEventLoggingDBname &&
			$title->equals( Title::newFromText( self::TITLE_TEXT, NS_MEDIAWIKI ) );
	}


	/**
	 * On EditFilterMerged, validate that the revised contents are valid JSON,
	 * and reject the edit otherwise.
	 *
	 * @param $editor EditPage
	 * @param $text string Content of the revised article
	 * @param &$error string Error message to return
	 * @param $summary string Edit summary provided for edit
	 */
	public static function onEditFilterMerged( $editor, $text, &$error, $summary ) {
		if ( !self::isModelsTitle( $editor->getTitle() ) ) {
			return true;
		}

		$models = FormatJson::decode( $text, true );
		if ( !is_array( $models ) ) {
			$error = '{{MediaWiki:InvalidJsonError}}'; // XXX(ori-l, 17-Nov-2012): i18n!
			return true;
		}

		return true;
	}


	/**
	 * On PageContentSaveComplete, check if the page getting saved is the
	 * models page. If so, update it in the cache.
	 *
	 * @return bool
	 */
	public static function onPageContentSaveComplete( $article, $user,
		$content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status,
		$baseRevId ) {

		global $wgMemc;

		if ( $revision === NULL ) {
			return true;
		}

		if ( !self::isModelsTitle( $article->getTitle() ) ) {
			return true;
		}

		$models = FormatJson::decode( $content->getNativeData(), true );
		if ( !is_array( $models ) ) {
			wfDebugLog( 'EventLogging', 'New data models revision fails to parse.' );
			return true;
		}

		$wgMemc->set( self::CACHE_KEY, $models );
		$wgMemc->set( self::CACHE_KEY . ':mTime',
			wfTimestamp( TS_UNIX, $revision->getTimestamp() ) );

		return true;
	}


	/**
	 * On ContentHandlerDefaultModelFor, specify JavaScript as the content
	 * model for the data models article.
	 *
	 * @param $title Title Specify model for this title.
	 * @param &$model string The desired model.
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( $title, &$model ) {

		if ( !self::isModelsTitle( $title ) ) {
			return true;
		}

		$model = CONTENT_MODEL_JAVASCRIPT;
		return false;
	}
}
