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

namespace TYPO3\CMS\Backend\Tests\Unit\Utility;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher;
use TYPO3\CMS\Backend\Tests\Unit\Utility\Fixtures\LabelFromItemListMergedReturnsCorrectFieldsFixture;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\NullFrontend;
use TYPO3\CMS\Core\Configuration\Loader\PageTsConfigLoader;
use TYPO3\CMS\Core\Configuration\PageTsConfig;
use TYPO3\CMS\Core\Configuration\Parser\PageTsConfigParser;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class BackendUtilityTest extends UnitTestCase
{
    use ProphecyTrait;

    protected bool $resetSingletonInstances = true;

    ///////////////////////////////////////
    // Tests concerning calcAge
    ///////////////////////////////////////
    /**
     * Data provider for calcAge function
     */
    public function calcAgeDataProvider(): array
    {
        return [
            'Single year' => [
                'seconds' => 60 * 60 * 24 * 365,
                'expectedLabel' => '1 year',
            ],
            'Plural years' => [
                'seconds' => 60 * 60 * 24 * 365 * 2,
                'expectedLabel' => '2 yrs',
            ],
            'Single negative year' => [
                'seconds' => 60 * 60 * 24 * 365 * -1,
                'expectedLabel' => '-1 year',
            ],
            'Plural negative years' => [
                'seconds' => 60 * 60 * 24 * 365 * 2 * -1,
                'expectedLabel' => '-2 yrs',
            ],
            'Single day' => [
                'seconds' => 60 * 60 * 24,
                'expectedLabel' => '1 day',
            ],
            'Plural days' => [
                'seconds' => 60 * 60 * 24 * 2,
                'expectedLabel' => '2 days',
            ],
            'Single negative day' => [
                'seconds' => 60 * 60 * 24 * -1,
                'expectedLabel' => '-1 day',
            ],
            'Plural negative days' => [
                'seconds' => 60 * 60 * 24 * 2 * -1,
                'expectedLabel' => '-2 days',
            ],
            'Single hour' => [
                'seconds' => 60 * 60,
                'expectedLabel' => '1 hour',
            ],
            'Plural hours' => [
                'seconds' => 60 * 60 * 2,
                'expectedLabel' => '2 hrs',
            ],
            'Single negative hour' => [
                'seconds' => 60 * 60 * -1,
                'expectedLabel' => '-1 hour',
            ],
            'Plural negative hours' => [
                'seconds' => 60 * 60 * 2 * -1,
                'expectedLabel' => '-2 hrs',
            ],
            'Single minute' => [
                'seconds' => 60,
                'expectedLabel' => '1 min',
            ],
            'Plural minutes' => [
                'seconds' => 60 * 2,
                'expectedLabel' => '2 min',
            ],
            'Single negative minute' => [
                'seconds' => 60 * -1,
                'expectedLabel' => '-1 min',
            ],
            'Plural negative minutes' => [
                'seconds' => 60 * 2 * -1,
                'expectedLabel' => '-2 min',
            ],
            'Zero seconds' => [
                'seconds' => 0,
                'expectedLabel' => '0 min',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider calcAgeDataProvider
     */
    public function calcAgeReturnsExpectedValues(int $seconds, string $expectedLabel): void
    {
        self::assertSame($expectedLabel, BackendUtility::calcAge($seconds));
    }

    ///////////////////////////////////////
    // Tests concerning getProcessedValue
    ///////////////////////////////////////
    /**
     * @test
     * @see https://forge.typo3.org/issues/20994
     */
    public function getProcessedValueForZeroStringIsZero(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'header' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                ],
            ],
        ];

        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();

        self::assertEquals('0', BackendUtility::getProcessedValue('tt_content', 'header', '0'));
    }

    /**
     * @test
     */
    public function getProcessedValueForGroup(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'multimedia' => [
                        'config' => [
                            'type' => 'group',
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();
        self::assertSame('testLabel', BackendUtility::getProcessedValue('tt_content', 'multimedia', '1,2'));
    }

    /**
     * @test
     */
    public function getProcessedValueForFlexNull(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'pi_flexform' => [
                        'config' => [
                            'type' => 'flex',
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();
        self::assertSame('', BackendUtility::getProcessedValue('tt_content', 'pi_flexform', null));
    }

    /**
     * @test
     */
    public function getProcessedValueForFlex(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'pi_flexform' => [
                        'config' => [
                            'type' => 'flex',
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturn('testLabel');
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();
        $expectation = "\n"
            . "\n    "
            . "\n        "
            . "\n            "
            . "\n                "
            . "\n                    bar"
            . "\n                "
            . "\n            "
            . "\n        "
            . "\n    "
            . "\n";

        self::assertSame($expectation, BackendUtility::getProcessedValue('tt_content', 'pi_flexform', '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDEF">
            <language index="lDEF">
                <field index="foo">
                    <value index="vDEF">bar</value>
                </field>
            </language>
        </sheet>
    </data>
</T3FlexForms>'));
    }

    /**
     * @test
     */
    public function getProcessedValueForGroupWithOneAllowedTable(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'pages' => [
                        'config' => [
                            'type' => 'group',
                            'allowed' => 'pages',
                            'maxitems' => 22,
                            'size' => 3,
                        ],
                    ],
                ],
            ],
            'pages' => [
                'ctrl' => [
                    'label' => 'title',
                ],
                'columns' => [
                    'title' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                ],
            ],
        ];

        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();

        $relationHandlerProphet = $this->prophesize(RelationHandler::class);
        $relationHandlerProphet->start(Argument::cetera())->shouldBeCalled();
        $relationHandlerProphet->getFromDB()->willReturn([]);
        $relationHandlerProphet->getResolvedItemArray()->willReturn([
            [
                'table' => 'pages',
                'uid' => 1,
                'record' => [
                    'uid' => 1,
                    'pid' => 0,
                    'title' => 'Page 1',
                ],
            ],
            [
                'table' => 'pages',
                'uid' => 2,
                'record' => [
                    'uid' => 2,
                    'pid' => 0,
                    'title' => 'Page 2',
                ],
            ],
        ]);
        GeneralUtility::addInstance(RelationHandler::class, $relationHandlerProphet->reveal());

        self::assertSame('Page 1, Page 2', BackendUtility::getProcessedValue('tt_content', 'pages', '1,2'));
    }

    /**
     * @test
     */
    public function getProcessedValueForGroupWithMultipleAllowedTables(): void
    {
        $GLOBALS['TCA'] = [
            'index_config' => [
                'ctrl' => [
                    'label' => 'title',
                ],
                'columns' => [
                    'title' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                    'indexcfgs' => [
                        'config' => [
                            'type' => 'group',
                            'allowed' => 'index_config,pages',
                            'size' => 5,
                        ],
                    ],
                ],
            ],
            'pages' => [
                'ctrl' => [
                    'label' => 'title',
                ],
                'columns' => [
                    'title' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                ],
            ],
        ];

        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();

        $relationHandlerProphet = $this->prophesize(RelationHandler::class);
        $relationHandlerProphet->start(Argument::cetera())->shouldBeCalled();
        $relationHandlerProphet->getFromDB()->willReturn([]);
        $relationHandlerProphet->getResolvedItemArray()->willReturn([
            [
                'table' => 'pages',
                'uid' => 1,
                'record' => [
                    'uid' => 1,
                    'pid' => 0,
                    'title' => 'Page 1',
                ],
            ],
            [
                'table' => 'index_config',
                'uid' => 2,
                'record' => [
                    'uid' => 2,
                    'pid' => 0,
                    'title' => 'Configuration 2',
                ],
            ],
        ]);
        GeneralUtility::addInstance(RelationHandler::class, $relationHandlerProphet->reveal());
        self::assertSame('Page 1, Configuration 2', BackendUtility::getProcessedValue('index_config', 'indexcfgs', 'pages_1,index_config_2'));
    }

    /**
     * @test
     */
    public function getProcessedValueForSelectWithMMRelation(): void
    {
        $relationHandlerProphet = $this->prophesize(RelationHandler::class);
        $relationHandlerProphet->start(Argument::cetera())->shouldBeCalled();
        $relationHandlerProphet->getFromDB()->willReturn([]);
        $relationHandlerProphet->getResolvedItemArray()->willReturn([
            [
                'table' => 'sys_category',
                'uid' => 1,
                'record' => [
                    'uid' => 2,
                    'pid' => 0,
                    'title' => 'Category 1',
                ],
            ],
            [
                'table' => 'sys_category',
                'uid' => 2,
                'record' => [
                    'uid' => 2,
                    'pid' => 0,
                    'title' => 'Category 2',
                ],
            ],
        ]);

        $relationHandlerInstance = $relationHandlerProphet->reveal();
        $relationHandlerInstance->tableArray['sys_category'] = [1, 2];

        GeneralUtility::addInstance(RelationHandler::class, $relationHandlerInstance);

        $GLOBALS['TCA'] = [
            'pages' => [
                'columns' => [
                    'categories' => [
                        'config' => [
                            'type' => 'select',
                            'foreign_table' => 'sys_category',
                            'MM' => 'sys_category_record_mm',
                            'MM_match_fields' => [
                                'fieldname' => 'categories',
                                'tablesnames' => 'pages',
                            ],
                            'MM_opposite_field' => 'items',
                        ],
                    ],
                ],
            ],
            'sys_category' => [
                'ctrl' => ['label' => 'title'],
                'columns' => [
                    'title' => [
                        'config' => [
                            'type' => 'input',
                        ],
                    ],
                    'items' => [
                        'config' => [
                            'type' => 'group',
                            'allowed' => '*',
                            'MM' => 'sys_category_record_mm',
                            'MM_oppositeUsage' => [],
                        ],
                    ],
                ],
            ],
        ];

        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();

        self::assertSame(
            'Category 1, Category 2',
            BackendUtility::getProcessedValue(
                'pages',
                'categories',
                '2',
                0,
                false,
                false,
                1
            )
        );
    }

    /**
     * @test
     */
    public function getProcessedValueDisplaysAgeForDateInputFieldsIfSettingAbsent(): void
    {
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturn(' min| hrs| days| yrs| min| hour| day| year');
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();

        $GLOBALS['EXEC_TIME'] = mktime(0, 0, 0, 8, 30, 2015);

        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'date' => [
                        'config' => [
                            'type' => 'datetime',
                            'format' => 'date',
                        ],
                    ],
                ],
            ],
        ];
        self::assertSame('28-08-15 (-2 days)', BackendUtility::getProcessedValue('tt_content', 'date', mktime(0, 0, 0, 8, 28, 2015)));
    }

    public function inputTypeDateDisplayOptions(): array
    {
        return [
            'typeSafe Setting' => [
                true,
                '28-08-15',
            ],
            'non typesafe setting' => [
                1,
                '28-08-15',
            ],
            'setting disabled typesafe' => [
                false,
                '28-08-15 (-2 days)',
            ],
            'setting disabled not typesafe' => [
                0,
                '28-08-15 (-2 days)',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider inputTypeDateDisplayOptions
     *
     * @param bool|int $input
     * @param string $expected
     */
    public function getProcessedValueHandlesAgeDisplayCorrectly($input, string $expected): void
    {
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturn(' min| hrs| days| yrs| min| hour| day| year');
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();

        $GLOBALS['EXEC_TIME'] = mktime(0, 0, 0, 8, 30, 2015);

        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'date' => [
                        'config' => [
                            'type' => 'datetime',
                            'format' => 'date',
                            'disableAgeDisplay' => $input,
                        ],
                    ],
                ],
            ],
        ];
        self::assertSame($expected, BackendUtility::getProcessedValue('tt_content', 'date', mktime(0, 0, 0, 8, 28, 2015)));
    }

    /**
     * @test
     */
    public function getProcessedValueForCheckWithSingleItem(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'hide' => [
                        'config' => [
                            'type' => 'check',
                            'items' => [
                                [
                                    0 => '',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:yes')->willReturn('Yes');
        $languageServiceProphecy->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:no')->willReturn('No');
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();
        self::assertSame('Yes', BackendUtility::getProcessedValue('tt_content', 'hide', 1));
    }

    /**
     * @test
     */
    public function getProcessedValueForCheckWithSingleItemInvertStateDisplay(): void
    {
        $GLOBALS['TCA'] = [
            'tt_content' => [
                'columns' => [
                    'hide' => [
                        'config' => [
                            'type' => 'check',
                            'items' => [
                                [
                                    0 => '',
                                    'invertStateDisplay' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:yes')->willReturn('Yes');
        $languageServiceProphecy->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:no')->willReturn('No');
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();
        self::assertSame('No', BackendUtility::getProcessedValue('tt_content', 'hide', 1));
    }

    public function getCommonSelectFieldsReturnsCorrectFieldsDataProvider(): array
    {
        return [
            'only uid' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [],
                'expectedFields' => 'uid',
            ],
            'label set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'label' => 'label',
                    ],
                ],
                'expectedFields' => 'uid,label',
            ],
            'label_alt set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'label_alt' => 'label,label2',
                    ],
                ],
                'expectedFields' => 'uid,label,label2',
            ],
            'versioningWS set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'versioningWS' => true,
                    ],
                ],
                'expectedFields' => 'uid,t3ver_state,t3ver_wsid',
            ],
            'selicon_field set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'selicon_field' => 'field',
                    ],
                ],
                'expectedFields' => 'uid,field',
            ],
            'typeicon_column set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'typeicon_column' => 'field',
                    ],
                ],
                'expectedFields' => 'uid,field',
            ],
            'enablecolumns set' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'enablecolumns' => [
                            'disabled' => 'hidden',
                            'starttime' => 'start',
                            'endtime' => 'stop',
                            'fe_group' => 'groups',
                        ],
                    ],
                ],
                'expectedFields' => 'uid,hidden,start,stop,groups',
            ],
            'label set to uid' => [
                'table' => 'test_table',
                'prefix' => '',
                'presetFields' => [],
                'tca' => [
                    'ctrl' => [
                        'label' => 'uid',
                    ],
                ],
                'expectedFields' => 'uid',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getCommonSelectFieldsReturnsCorrectFieldsDataProvider
     */
    public function getCommonSelectFieldsReturnsCorrectFields(
        string $table,
        string $prefix,
        array $presetFields,
        array $tca,
        string $expectedFields = ''
    ): void {
        $GLOBALS['TCA'][$table] = $tca;
        $selectFields = BackendUtility::getCommonSelectFields($table, $prefix, $presetFields);
        self::assertEquals($selectFields, $expectedFields);
    }

    public function getLabelFromItemlistReturnsCorrectFieldsDataProvider(): array
    {
        return [
            'item set' => [
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '1',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['Item 1', '0'],
                                    ['Item 2', '1'],
                                    ['Item 3', '3'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => 'Item 2',
            ],
            'item set twice' => [
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '1',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['Item 1', '0'],
                                    ['Item 2a', '1'],
                                    ['Item 2b', '1'],
                                    ['Item 3', '3'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => 'Item 2a',
            ],
            'item not found' => [
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '5',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['Item 1', '0'],
                                    ['Item 2', '1'],
                                    ['Item 3', '2'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => null,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getLabelFromItemlistReturnsCorrectFieldsDataProvider
     */
    public function getLabelFromItemlistReturnsCorrectFields(
        string $table,
        string $col,
        string $key,
        array $tca,
        ?string $expectedLabel = ''
    ): void {
        $GLOBALS['TCA'][$table] = $tca;
        $label = BackendUtility::getLabelFromItemlist($table, $col, $key);
        self::assertEquals($label, $expectedLabel);
    }

    public function getLabelFromItemListMergedReturnsCorrectFieldsDataProvider(): array
    {
        return [
            'no field found' => [
                'pageId' => '123',
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '10',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['Item 1', '0'],
                                    ['Item 2', '1'],
                                    ['Item 3', '3'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => '',
            ],
            'no tsconfig set' => [
                'pageId' => '123',
                'table' => 'tt_content',
                'col' => 'menu_type',
                'key' => '1',
                'tca' => [
                    'columns' => [
                        'menu_type' => [
                            'config' => [
                                'items' => [
                                    ['Item 1', '0'],
                                    ['Item 2', '1'],
                                    ['Item 3', '3'],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedLabel' => 'Item 2',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getLabelFromItemListMergedReturnsCorrectFieldsDataProvider
     */
    public function getLabelFromItemListMergedReturnsCorrectFields(
        string $pageId,
        string $table,
        string $column,
        string $key,
        array $tca,
        string $expectedLabel = ''
    ): void {
        $GLOBALS['TCA'][$table] = $tca;

        self::assertEquals($expectedLabel, LabelFromItemListMergedReturnsCorrectFieldsFixture::getLabelFromItemListMerged($pageId, $table, $column, $key));
    }

    /**
     * @test
     */
    public function getFuncCheckReturnsInputTagWithValueAttribute(): void
    {
        self::assertStringMatchesFormat('<input %Svalue="1"%S/>', BackendUtility::getFuncCheck('params', 'test', true));
    }

    public function getLabelsFromItemsListDataProvider(): array
    {
        return [
            'return value if found' => [
                'foobar', // table
                'someColumn', // col
                'foo, bar', // keyList
                [ // TCA
                    'columns' => [
                        'someColumn' => [
                            'config' => [
                                'items' => [
                                    '0' => ['aFooLabel', 'foo'],
                                    '1' => ['aBarLabel', 'bar'],
                                ],
                            ],
                        ],
                    ],
                ],
                [], // page TSconfig
                'aFooLabel, aBarLabel', // expected
            ],
            'page TSconfig overrules TCA' => [
                'foobar', // table
                'someColumn', // col
                'foo,bar, add', // keyList
                [ // TCA
                    'columns' => [
                        'someColumn' => [
                            'config' => [
                                'items' => [
                                    '0' => ['aFooLabel', 'foo'],
                                    '1' => ['aBarLabel', 'bar'],
                                ],
                            ],
                        ],
                    ],
                ],
                [ // page TSconfig
                    'addItems.' => ['add' => 'aNewLabel'],
                    'altLabels.' => ['bar' => 'aBarDiffLabel'],
                ],
                'aFooLabel, aBarDiffLabel, aNewLabel', // expected
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getLabelsFromItemsListDataProvider
     */
    public function getLabelsFromItemsListReturnsCorrectValue(
        string $table,
        string $col,
        string $keyList,
        array $tca,
        array $pageTsConfig,
        string $expectedLabel
    ): void {
        // Stub LanguageService and let sL() return the same value that came in again
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $GLOBALS['LANG']->method('sL')->willReturnArgument(0);

        $GLOBALS['TCA'][$table] = $tca;
        $label = BackendUtility::getLabelsFromItemsList($table, $col, $keyList, $pageTsConfig);
        self::assertEquals($expectedLabel, $label);
    }

    /**
     * @test
     */
    public function getProcessedValueReturnsLabelsForExistingValuesSolely(): void
    {
        $table = 'foobar';
        $col = 'someColumn';
        $tca = [
            'columns' => [
                'someColumn' => [
                    'config' => [
                        'type' => 'select',
                        'items' => [
                            '0' => ['aFooLabel', 'foo'],
                            '1' => ['aBarLabel', 'bar'],
                        ],
                    ],
                ],
            ],
        ];
        // Stub LanguageService and let sL() return the same value that came in again
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $GLOBALS['LANG']->method('sL')->willReturnArgument(0);

        $GLOBALS['TCA'][$table] = $tca;
        $label = BackendUtility::getProcessedValue($table, $col, 'foo,invalidKey,bar');
        self::assertEquals('aFooLabel, aBarLabel', $label);
    }

    /**
     * @test
     */
    public function getProcessedValueReturnsPlainValueIfItemIsNotFound(): void
    {
        $table = 'foobar';
        $col = 'someColumn';
        $tca = [
            'columns' => [
                'someColumn' => [
                    'config' => [
                        'type' => 'select',
                        'items' => [
                            '0' => ['aFooLabel', 'foo'],
                        ],
                    ],
                ],
            ],
        ];
        // Stub LanguageService and let sL() return the same value that came in again
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $GLOBALS['LANG']->method('sL')->willReturnArgument(0);

        $GLOBALS['TCA'][$table] = $tca;
        $label = BackendUtility::getProcessedValue($table, $col, 'invalidKey');
        self::assertEquals('invalidKey', $label);
    }

    /**
     * @test
     */
    public function dateTimeAgeReturnsCorrectValues(): void
    {
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->sL(Argument::cetera())->willReturn(' min| hrs| days| yrs| min| hour| day| year');
        $GLOBALS['LANG'] = $languageServiceProphecy->reveal();
        $GLOBALS['EXEC_TIME'] = mktime(0, 0, 0, 3, 23, 2016);

        self::assertSame('24-03-16 00:00 (-1 day)', BackendUtility::dateTimeAge($GLOBALS['EXEC_TIME'] + 86400));
        self::assertSame('24-03-16 (-1 day)', BackendUtility::dateTimeAge($GLOBALS['EXEC_TIME'] + 86400, 1, 'date'));
    }

    /**
     * @test
     */
    public function purgeComputedPropertyNamesRemovesPropertiesStartingWithUnderscore(): void
    {
        $propertyNames = [
            'uid',
            'pid',
            '_ORIG_PID',
        ];
        $computedPropertyNames = BackendUtility::purgeComputedPropertyNames($propertyNames);
        self::assertSame(['uid', 'pid'], $computedPropertyNames);
    }

    /**
     * @test
     */
    public function purgeComputedPropertiesFromRecordRemovesPropertiesStartingWithUnderscore(): void
    {
        $record = [
            'uid'       => 1,
            'pid'       => 2,
            '_ORIG_PID' => 1,
        ];
        $expected = [
            'uid' => 1,
            'pid' => 2,
        ];
        $computedProperties = BackendUtility::purgeComputedPropertiesFromRecord($record);
        self::assertSame($expected, $computedProperties);
    }

    public function splitTableUidDataProvider(): array
    {
        return [
            'simple' => [
                'pages_23',
                ['pages', '23'],
            ],
            'complex' => [
                'tt_content_13',
                ['tt_content', '13'],
            ],
            'multiple underscores' => [
                'tx_runaway_domain_model_crime_scene_1234',
                ['tx_runaway_domain_model_crime_scene', '1234'],
            ],
            'no underscore' => [
                'foo',
                ['', 'foo'],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider splitTableUidDataProvider
     */
    public function splitTableUid($input, $expected): void
    {
        $result = BackendUtility::splitTable_Uid($input);
        self::assertSame($expected, $result);
    }

    /**
     * Tests if the method getPagesTSconfig can be called without having a GLOBAL['BE_USER'] object.
     * However, this test also shows all the various other dependencies this method has.
     *
     * @test
     */
    public function getPagesTSconfigWorksWithoutInitializedBackendUser(): void
    {
        $expected = ['called.' => ['config']];
        $pageId = 13;
        $eventDispatcherProphecy = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcherProphecy->dispatch(Argument::any())->willReturnArgument();
        $loader = new PageTsConfigLoader($eventDispatcherProphecy->reveal());
        $parserProphecy = $this->prophesize(PageTsConfigParser::class);
        $parserProphecy->parse(Argument::cetera())->willReturn($expected);
        $configuration = new PageTsConfig(new NullFrontend('runtimeCache'), $loader, $parserProphecy->reveal());
        GeneralUtility::addInstance(PageTsConfig::class, $configuration);

        $matcherProphecy = $this->prophesize(ConditionMatcher::class);
        GeneralUtility::addInstance(ConditionMatcher::class, $matcherProphecy->reveal());

        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId($pageId)->willReturn(
            new Site('dummy', $pageId, ['base' => 'https://example.com'])
        );
        GeneralUtility::addInstance(SiteFinder::class, $siteFinder->reveal());

        $cacheManagerProphecy = $this->prophesize(CacheManager::class);
        $cacheProphecy = $this->prophesize(FrontendInterface::class);
        $cacheManagerProphecy->getCache('runtime')->willReturn($cacheProphecy->reveal());
        $cacheProphecy->has(Argument::cetera())->willReturn(false);
        $cacheProphecy->get(Argument::cetera())->willReturn(false);
        $cacheProphecy->set(Argument::cetera())->willReturn(false);
        $cacheProphecy->get('backendUtilityBeGetRootLine')->willReturn(['13--1' => []]);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerProphecy->reveal());

        $result = BackendUtility::getPagesTSconfig($pageId);
        self::assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function returnNullForMissingTcaConfigInResolveFileReferences(): void
    {
        $tableName = 'table_a';
        $fieldName = 'field_a';
        $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] = [];
        self::assertNull(BackendUtility::resolveFileReferences($tableName, $fieldName, []));
    }

    /**
     * @test
     * @dataProvider unfitResolveFileReferencesTableConfig
     */
    public function returnNullForUnfitTableConfigInResolveFileReferences(array $config): void
    {
        $tableName = 'table_a';
        $fieldName = 'field_a';
        $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] = $config;
        self::assertNull(BackendUtility::resolveFileReferences($tableName, $fieldName, []));
    }

    public function unfitResolveFileReferencesTableConfig(): array
    {
        return [
            'invalid table' => [
                [
                    'type' => 'inline',
                    'foreign_table' => 'table_b',
                ],
            ],
            'empty table' => [
                [
                    'type' => 'inline',
                    'foreign_table' => '',
                ],
            ],
            'invalid type' => [
                [
                    'type' => 'select',
                    'foreign_table' => 'sys_file_reference',
                ],
            ],
            'empty type' => [
                [
                    'type' => '',
                    'foreign_table' => 'sys_file_reference',
                ],
            ],
            'empty' => [
                [
                    'type' => '',
                    'foreign_table' => '',
                ],
            ],
        ];
    }

    /**
     * @test
     */
    public function workspaceOLDoesNotChangeValuesForNoBeUserAvailable(): void
    {
        $GLOBALS['BE_USER'] = null;
        $tableName = 'table_a';
        $row = [
            'uid' => 1,
            'pid' => 17,
        ];
        $reference = $row;
        BackendUtility::workspaceOL($tableName, $row);
        self::assertSame($reference, $row);
    }

    /**
     * @test
     */
    public function resolveFileReferencesReturnsEmptyResultForNoReferencesAvailable(): void
    {
        $tableName = 'table_a';
        $fieldName = 'field_a';
        $relationHandlerProphecy = $this->prophesize(RelationHandler::class);
        $relationHandlerProphecy->start(
            'foo',
            'sys_file_reference',
            '',
            42,
            $tableName,
            ['type' => 'inline', 'foreign_table' => 'sys_file_reference']
        )->shouldBeCalled();
        $relationHandlerProphecy->processDeletePlaceholder()->shouldBeCalled();
        $relationHandler = $relationHandlerProphecy->reveal();
        $relationHandler->tableArray = ['sys_file_reference' => []];
        GeneralUtility::addInstance(RelationHandler::class, $relationHandler);
        $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'] = [
            'type' => 'inline',
            'foreign_table' => 'sys_file_reference',
        ];
        $elementData = [
            $fieldName => 'foo',
            'uid' => 42,
        ];

        self::assertEmpty(BackendUtility::resolveFileReferences($tableName, $fieldName, $elementData));
    }

    /**
     * @test
     */
    public function wsMapIdReturnsLiveIdIfNoBeUserIsAvailable(): void
    {
        $GLOBALS['BE_USER'] = null;
        $tableName = 'table_a';
        $uid = 42;
        self::assertSame(42, BackendUtility::wsMapId($tableName, $uid));
    }

    /**
     * @test
     */
    public function getAllowedFieldsForTableReturnsEmptyArrayOnBrokenTca(): void
    {
        $GLOBALS['BE_USER'] = new BackendUserAuthentication();
        self::assertEmpty(BackendUtility::getAllowedFieldsForTable('myTable', false));
    }

    /**
     * @test
     */
    public function getAllowedFieldsForTableReturnsUniqueList(): void
    {
        $GLOBALS['BE_USER'] = new BackendUserAuthentication();
        $GLOBALS['TCA']['myTable'] = [
            'ctrl'=> [
                'tstamp' => 'updatedon',
                // Won't be added due to defined in "columns"
                'crdate' => 'createdon',
                'cruser_id' => 'createdby',
                'sortby' => 'sorting',
                'versioningWS' => true,
            ],
            'columns' => [
                // Regular field
                'title' => [
                    'config' => [
                        'type' => 'input',
                    ],
                ],
                // Overwrite automatically set management field from "ctrl"
                'createdon' => [
                    'config' => [
                        'type' => 'input',
                    ],
                ],
                // Won't be added due to type "none"
                'reference' => [
                    'config' => [
                        'type' => 'none',
                    ],
                ],
            ],
        ];

        self::assertEquals(
            ['title', 'createdon', 'uid', 'pid', 'updatedon', 'createdby', 'sorting', 't3ver_state', 't3ver_wsid', 't3ver_oid'],
            BackendUtility::getAllowedFieldsForTable('myTable', false)
        );
    }
}
