.. include:: /Includes.rst.txt

====================================================
Breaking: #96517 - HMENU.collapse Typoscript removed
====================================================

See :issue:`96517`

Description
===========

The :typoscript:`collapse` TypoScript property of HMENU is removed without
substitution.

When set, active HMENU items previously linked to their parent page,
which was primarily a use-case for GMENU_LAYERS, which was
removed in TYPO3 6.0.


Impact
======

Setting this TypoScript option has no effect anymore.


Affected Installations
======================

TYPO3 installations with HMENU definitions having this option
set which is highly unlikely.


Migration
=========

Use a custom user function or the PSR-14 :php:`FilterMenuItemsEvent` to modify
the menu items.

.. index:: Frontend, TypoScript, NotScanned, ext:frontend
