<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Beuser\ViewHelpers;

use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\ViewHelpers\Be\AbstractBackendViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Views sprite icon for a record (object)
 *
 * @internal
 */
final class SpriteIconForRecordViewHelper extends AbstractBackendViewHelper
{
    /**
     * As this ViewHelper renders HTML, the output must not be escaped.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('table', 'string', '', true);
        $this->registerArgument('object', 'object', '', true);
    }

    /**
     * Displays spriteIcon for database table and object
     */
    public function render(): string
    {
        return self::renderStatic($this->arguments, $this->buildRenderChildrenClosure(), $this->renderingContext);
    }

    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        $object = $arguments['object'];
        $table = $arguments['table'];

        if (!method_exists($object, 'getUid')) {
            return '';
        }
        $row = [
            'uid' => $object->getUid(),
            'startTime' => false,
            'endTime' => false,
        ];
        if (method_exists($object, 'getIsDisabled')) {
            $row['disable'] = $object->getIsDisabled();
        }
        if (method_exists($object, 'getHidden')) {
            $row['hidden'] = $object->getHidden();
        }
        if (method_exists($object, 'getStartDateAndTime')) {
            $row['startTime'] = $object->getStartDateAndTime();
        }
        if (method_exists($object, 'getEndDateAndTime')) {
            $row['endTime'] = $object->getEndDateAndTime();
        }
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        return $iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL)->render();
    }
}
