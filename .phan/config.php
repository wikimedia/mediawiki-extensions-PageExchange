<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['suppress_issue_types'] = array_merge(
	$cfg['suppress_issue_types'],
	[
		// Suppress issue types that currently exist in the codebase.
		'MediaWikiNoBaseException',
		'PhanParamTooFewInPHPDoc',
		'PhanPluginNeverReturnMethod',
		'PhanPossiblyUndeclaredVariable',
		'PhanRedundantCondition',
		'PhanThrowTypeAbsent',
		'PhanTypeExpectedObjectPropAccess',
		'PhanTypeInvalidLeftOperandOfNumericOp',
		'PhanTypeMismatchArgument',
		'PhanTypeMismatchArgumentInternal',
		'PhanTypeMismatchArgumentProbablyReal',
		'PhanTypeMismatchArgumentReal',
		'PhanTypeMismatchReturn',
		'PhanTypePossiblyInvalidDimOffset',
		'PhanTypeSuspiciousStringExpression',
		'PhanUndeclaredClassMethod',
		'PhanUndeclaredClassStaticProperty',
		'PhanUndeclaredMethod',
		'PhanUndeclaredTypeParameter',
		'PhanUndeclaredTypeReturnType',
		'PhanUndeclaredVariable',
		'SecurityCheck-DoubleEscaped',
		'SecurityCheck-XSS'
	]
);

return $cfg;
