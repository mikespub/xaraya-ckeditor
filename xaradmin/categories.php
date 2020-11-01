<?php
/**
 * crispBB Forum Module
 *
 * @package modules
 * @copyright (C) 2008-2009 The Digital Development Foundation
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link http://www.xaraya.com
 *
 * @subpackage crispBB Forum Module
 * @link http://xaraya.com/index.php/release/970.html
 * @author crisp <crisp@crispcreations.co.uk>
 */
/**
 * Function to do something
 *
 * @author crisp <crisp@crispcreations.co.uk>
 * What this function does
 *
 * @return array
 */
function crispbb_admin_categories($args)
{
    if (!xarSecurity::check('AdminCrispBB') || !xarSecurity::check('ManageCategories')) {
        return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
    }

    extract($args);
    if (!xarVar::fetch('sublink', 'pre:trim:lower:str:1', $sublink, '', xarVar::NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('phase', 'pre:trim:lower:str:1', $phase, 'form', xarVar::NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('confirm', 'checkbox', $confirm, false, xarVar::NOT_REQUIRED)) {
        return;
    }

    $data = array();
    $basecats = xarMod::apiFunc('categories', 'user', 'getallcatbases', array('module' => 'crispbb'));
    $basecid = count($basecats) > 0 ? $basecats[0]['category_id'] : null;
    $data['options'] = array();
    if (!empty($basecid)) {
        $data['options'][] = array('cid' => $basecid);
    }
    $data['sublink'] = $sublink;

    if ($sublink == 'mastercat') {
        if ($phase == 'update') {
            if (!$confirm) {
                // @TODO:
                //$data['sublink'] = 'confirm';
                //return $data;
            }
            // Confirm authorisation code.
            if (!xarSec::confirmAuthKey()) {
                return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'bad_author'));
            }
            sys::import('modules.dynamicdata.class.properties.master');
            $picker = DataPropertyMaster::getProperty(array('name' => 'categorypicker'));
            $isvalid = $picker->checkInput('basecid');
            xarResponse::Redirect(xarController::URL('crispbb', 'admin', 'categories', array('sublink' => 'mastercat')));
        }
        $data['authid'] = xarSec::genAuthKey();
    }
    $data['menulinks'] = xarMod::apiFunc(
        'crispbb',
        'admin',
        'getmenulinks',
        array(
            'current_module' => 'crispbb',
            'current_type' => 'admin',
            'current_func' => 'categories',
            'current_sublink' => $sublink,
        )
    );
    return $data;
}
