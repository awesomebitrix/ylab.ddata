<?php

namespace Ylab\Ddata\Entity;

use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserFieldTable;
use Ylab\Ddata\Interfaces\EntityUnitClass;
use Ylab\Ddata\Helpers;

Loc::loadMessages(__FILE__);

/**
 * Class HighloadblockElement
 * @package Ylab\Ddata\entity
 */
class HighloadblockElement extends EntityUnitClass
{
    /**
     * @var null
     */
    public $iHighloadblockId = null;
    /**
     * @var \Bitrix\Main\Entity\Base|null
     */
    public $oEntity = null;

    /**
     * @return array
     */
    public static function getDescription()
    {
        return [
            "ID" => "highloadblock-element",
            "NAME" => Loc::getMessage('YLAB_DDATA_HLELEM_ENTITY_NAME'),
            "DESCRIPTION" => Loc::getMessage('YLAB_DDATA_HLELEM_ENTITY_DESCRIPTION'),
            "TYPE" => "highloadblock-element",
            "CLASS" => __CLASS__
        ];
    }

    /**
     * HighloadblockElement constructor.
     * @param $iProfileID
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function __construct($iProfileID)
    {
        Loader::includeModule("highloadblock");

        parent::__construct($iProfileID);
        if (!empty($this->profile['OPTIONS']['highloadblock_id'])) {
            $this->iHighloadblockId = $this->profile['OPTIONS']['highloadblock_id'];
            $rsData = HighloadBlockTable::getList([
                'filter' => [
                    'ID' => $this->iHighloadblockId
                ]
            ]);
            if (($arData = $rsData->fetch())) {
                $this->oEntity = HighloadBlockTable::compileEntity($arData);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public static function getPrepareForm(HttpRequest $oRequest)
    {
        Loader::includeModule("highloadblock");

        $oResultHlb = HighloadBlockTable::getList([
            'runtime' => [
                new ReferenceField('LANG', HighloadBlockLangTable::class, [
                    '=this.ID' => 'ref.ID',
                    'ref.LID' => new SqlExpression("?s", LANGUAGE_ID)
                ])
            ],
            'select' => ['ID', 'NAME', 'LANG_NAME' => 'LANG.NAME']
        ]);

        $arResultHlb = $oResultHlb->fetchAll();
        $arRequest = $oRequest->toArray();

        ob_start();
        include Helpers::getModulePath() . "/admin/fragments/highloadblock_element_prepare_form.php";
        $tpl = ob_get_contents();
        ob_end_clean();

        return $tpl;
    }

    /**
     * @inheritdoc
     */
    public static function isValidPrepareForm(HttpRequest $oRequest)
    {
        Loader::includeModule("highloadblock");

        $arRequest = $oRequest->toArray();
        $sHighloadblockId = $arRequest['prepare']['highloadblock_id'];

        $iResultHlb = HighloadBlockTable::getCount([
            '=ID' => $sHighloadblockId
        ]);

        if ($iResultHlb > 0) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getFields(HttpRequest $oRequest = null)
    {
        $arFields = [
            'FIELDS' => [],
            'PROPERTIES' => [],
        ];
        $iHighloadblockId = $this->iHighloadblockId;;

        if ($oRequest && empty($iHighloadblockId)) {
            $arPrepareRequest = $oRequest->get('prepare');
            if (empty($iHighloadblockId)) {
                $iHighloadblockId = $arPrepareRequest['highloadblock_id'];
            } else {
                return $arFields;
            }
        }

        $oResultFields = UserFieldTable::getList([
            'runtime' => [
                new ExpressionField('LANG_NAME', '(
                    SELECT `EDIT_FORM_LABEL` 
                    FROM `b_user_field_lang`
                    WHERE `USER_FIELD_ID`=`main_user_field`.`ID` AND 
                          `LANGUAGE_ID`=' . new SqlExpression("?s", LANGUAGE_ID) . '
                )')
            ],
            'filter' => [
                '=ENTITY_ID' => 'HLBLOCK_' . $iHighloadblockId
            ],
            'select' => ['LANG_NAME', 'FIELD_NAME', 'USER_TYPE_ID', 'MULTIPLE', 'MANDATORY']
        ]);

        $arResultFields = $oResultFields->fetchAll();

        foreach ($arResultFields as $field) {
            $type = [];
            switch ($field['USER_TYPE_ID']) {
                case 'video' :
                    $type = [/*TODO: Пока нет генератора*/];
                    break;
                case 'hlblock' :
                    $type = ['hl.element'];
                    break;
                case 'string':
                    $type = ['string', 'integer'];
                    break;
                case 'integer':
                    $type = ['integer'];
                    break;
                case 'double' :
                    $type = ['float'];
                    break;
                case 'datetime':
                case 'date':
                    $type = ['datetime'];
                    break;
                case 'boolean':
                    $type = ['checkbox'];
                    break;
                case 'address':
                    $type = ['string'];
                    break;
                case 'url':
                    $type = ['string'];
                    break;
                case 'file':
                    $type = ['file'];
                    break;
                case 'enumeration':
                    $type = ['enum.hl'];
                    break;
                case 'iblock_section':
                    $type = ['iblock.section'];
                    break;
                case 'iblock_element':
                    $type = ['iblock.element'];
                    break;
                case 'string_formatted':
                    $type = ['string'];
                    break;
                case 'vote':
                    $type = [/*TODO: Пока нет генератора*/];
                    break;
                case 'url_preview':
                    $type = [/*TODO: Пока нет генератора*/];
                    break;
            }

            $arFields['FIELDS'][$field['FIELD_NAME']] = [
                'title' => $field['LANG_NAME'],
                'required' => $field['MANDATORY'] == 'Y',
                'type' => $type
            ];
        }

        return $arFields;
    }

    /**
     * @return array|mixed
     * @throws \Exception
     */
    public function genUnit()
    {
        if (empty($this->oEntity)) {
            return [];
        }

        $oDataClass = $this->oEntity->getDataClass();
        $arFieldsProfile = $this->profile['FIELDS'];
        $arLoadFields = [];
        $arResult = [];

        foreach ($arFieldsProfile as $arField) {
            $arLoadFields[$arField['FIELD_CODE']] = $arField['OBJECT']->getValue();
        }

        $oResult = $oDataClass::add($arLoadFields);

        if ($oResult->isSuccess()) {
            $arResult['NEW_ELEMENT_ID'] = $oResult->getId();
        } else {
            $arResult['ERROR'] = $oResult->getErrorMessages();
        }

        return $arResult;
    }
}