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
 *//**
 * Do something
 *
 * Standard function
 *
 * @author crisp <crisp@crispcreations.co.uk>
 * @return array
 * @throws none
 */
 sys::import('modules.base.class.pager');
function crispbb_user_moderate($args)
{
    extract($args);
    if (!xarVar::fetch('component', 'enum:topics:posts:waiting', $component, 'topics', XARVAR_NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('modaction', 'str', $modaction, null, XARVAR_NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('phase', 'enum:update:form', $phase, 'form', XARVAR_NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('confirm', 'checkbox', $confirm, false, XARVAR_NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('startnum', 'int', $startnum, null, XARVAR_NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('sort', 'enum:ttitle:ttime:towner:ptime:powner:pid', $sort, 'ttime', XARVAR_NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('order', 'enum:ASC:DESC:asc:desc', $order, 'DESC', XARVAR_NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('return_url', 'str:1', $return_url, '', XARVAR_NOT_REQUIRED)) {
        return;
    }

    $forums = xarMod::apiFunc(
        'crispbb',
        'user',
        'getforums',
        array('tstatus' => array(1,2,3,4,5), 'privcheck' => true)
    );
    if (isset($forums['error'])) {
        if ($forums['error'] == 'BAD_DATA') {
            return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'bad_author'));
        } else {
            return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
        }
    }

    $uid = xarUser::getVar('id');
    $now = time();
    $invalid = array();
    $pageTitle = xarML('Moderate');
    $numitems = 10;
    $presets = xarMod::apiFunc(
        'crispbb',
        'user',
        'getpresets',
        array('preset' => 'tstatusoptions,pstatusoptions,sortorderoptions')
    );
    $tracker = unserialize(xarModUserVars::get('crispbb', 'tracker_object'));

    switch ($component) {
        case 'waiting':
            $pageTitle = xarML('Waiting Content');
        break;
        case 'topics':
            if (!xarVar::fetch('fid', 'id', $fid, null, XARVAR_NOT_REQUIRED)) {
                return;
            }
            if (!xarVar::fetch('tids', 'list', $tids, array(), XARVAR_NOT_REQUIRED)) {
                return;
            }
            if ($modaction != 'move') {
                if (!xarVar::fetch('tstatus', 'int', $tstatus, 0, XARVAR_NOT_REQUIRED)) {
                    return;
                }
                $forumoptions = array();
                //$forumoptions[0] = array('id' => '0', 'name' => xarML('All Forums'));
                foreach ($forums as $fkey => $fval) {
                    //if (empty($fval['modforumurl'])) continue;
                    if (empty($fid)) {
                        $fid = $fkey;
                    }
                    $forumoptions[$fkey] = array('id' => $fkey, 'name' => $fval['fname']);
                }
                if (empty($forumoptions[$fid])) {
                    return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
                }
                $data = $forums[$fid];

                if ($phase == 'update') {
                    // do validations for current action
                    $seentids = array();
                    if (!empty($tids) && is_array($tids)) {
                        foreach ($tids as $seentid => $checked) {
                            if (empty($seentid) || empty($checked)) {
                                continue;
                            }
                            $seentids[$seentid] = 1;
                        }
                        $seentids = !empty($seentids) ? array_keys($seentids) : array();
                    }
                    if (empty($seentids) || !is_array($seentids)) {
                        $invalid['tids'] = xarML('No topics selected for this action');
                    }
                    $topics = xarMod::apiFunc(
                        'crispbb',
                        'user',
                        'gettopics',
                        array('tid' => $seentids, 'fid' => $fid, 'numsubs' => true)
                    );

                    if (empty($topics)) {
                        $invalid['tids'] = xarML('No topics found');
                    }

                    // take no chances here, devious users could have changed input manually
                    // so we check each topic to make sure the user has adequate privs
                    $allowed = true;
                    foreach ($topics as $tcheck) {
                        switch ($modaction) {
                            case 'open':
                            case 'close':
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'checkseclevel',
                                    array('check' => $tcheck, 'priv' => 'closetopics')
                                )) {
                                    $allowed = false;
                                }
                            break;
                            case 'approve':
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'checkseclevel',
                                    array('check' => $tcheck, 'priv' => 'approvetopics')
                                )) {
                                    $allowed = false;
                                }
                            break;
                            case 'move':
                                    $allowed = false;
                            break;
                            case 'lock':
                            case 'unlock':
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'checkseclevel',
                                    array('check' => $tcheck, 'priv' => 'locktopics')
                                )) {
                                    $allowed = false;
                                }
                            break;
                            case 'delete':
                            case 'undelete':
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'checkseclevel',
                                    array('check' => $tcheck, 'priv' => 'deletetopics')
                                )) {
                                    $allowed = false;
                                }
                            break;
                            case 'purge':
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'checkseclevel',
                                    array('check' => $tcheck, 'priv' => 'editforum')
                                )) {
                                    $allowed = false;
                                }
                            break;
                        }
                    }

                    // a failure here generally means the user manually changed params somewhere
                    if (!$allowed) {
                        return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
                    }

                    if (empty($invalid)) {
                        // check for confirmation
                        if (!$confirm) {
                            // pass data to confirmation template
                            $data['topics'] = $topics;
                            $data['modaction'] = $modaction;
                            $data['component'] = $component;
                            $data['tstatus'] = $tstatus;
                            $data['pageTitle'] = xarML('Confirm Action');
                            $data['return_url'] = $return_url;
                            return xarTPLModule('crispbb', 'user', 'moderate-confirm', $data);
                        }
                        // finally, perform requested action
                        if (!xarSecConfirmAuthKey()) {
                            return;
                        }
                        $seenposters = array();
                        // perform the action on each topic in turn
                        foreach ($topics as $tid => $topic) {
                            switch ($modaction) {
                                case 'open':
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatetopic',
                                        array(
                                            'tid' => $topic['tid'],
                                            'tstatus' => 0,
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                break;
                                case 'close':
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatetopic',
                                        array(
                                            'tid' => $topic['tid'],
                                            'tstatus' => 1,
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                break;
                                case 'approve':
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatetopic',
                                        array(
                                            'tid' => $topic['tid'],
                                            'tstatus' => 0,
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                    // update the forum last topic
                                    $lasttopic = xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'getposts',
                                        array(
                                            'numitems' => 1,
                                            'fid' => $topic['fid'],
                                            'sort' => 'ptime',
                                            'order' => 'DESC',
                                            'tstatus' => array(0,1,2,4),
                                            'pstatus' => 0
                                    )
                                    );
                                    $lasttopic = !empty($lasttopic) && is_array($lasttopic) ? reset($lasttopic) : array();
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'admin',
                                        'update',
                                        array(
                                            'fid' => $topic['fid'],
                                            'lasttid' => $lasttopic['tid'],
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                    if ($lasttopic['tid'] == $topic['tid']) {
                                        // update the forum tracker
                                        $fstring = xarModVars::get('crispbb', 'ftracking');
                                        $ftracking = (!empty($fstring)) ? unserialize($fstring) : array();
                                        $ftracking[$topic['fid']] = $topic['ptime'];
                                        xarModVars::set('crispbb', 'ftracking', serialize($ftracking));
                                    }
                                    // ok to let subscribers know about this topic now
                                    if (xarMod::isAvailable('crispsubs')) {
                                        if (xarModIsHooked('crispsubs', 'crispbb', $topic['topicstype'])) {
                                            xarMod::apiFunc(
                                                'crispsubs',
                                                'user',
                                                'createhook',
                                                array(
                                                    'modname' => 'crispbb',
                                                    'itemtype' => $topic['topicstype'],
                                                    'objectid' => $tid
                                                )
                                            );
                                        }
                                    }
                                break;

                                /* // don't need disapprove, just delete, or lock
                                case 'disapprove': // status?
                                    if (!xarMod::apiFunc('crispbb', 'user', 'updatetopic',
                                        array(
                                            'tid' => $topic['tid'],
                                            'tstatus' => 6,
                                            'nohooks' => true
                                        ))) return;
                                break;
                                */
                                case 'unlock':
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatetopic',
                                        array(
                                            'tid' => $topic['tid'],
                                            'tstatus' => 0,
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                break;
                                case 'lock':
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatetopic',
                                        array(
                                            'tid' => $topic['tid'],
                                            'tstatus' => 4,
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                break;
                                case 'delete':
                                    // store current tstatus before deleting
                                    // especially important for moved topics
                                    $tsettings = $topic['tsettings'];
                                    $tsettings['oldtstatus'][] = $topic['tstatus'];
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatetopic',
                                        array(
                                            'tid' => $topic['tid'],
                                            'tstatus' => 5,
                                            'tsettings' => $tsettings,
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                break;
                                case 'undelete':
                                    // restore previous tstatus
                                    // especially important for moved topics
                                    $tsettings = $topic['tsettings'];
                                    if (!empty($tsettings['oldtstatus'])) {
                                        $oldtstatus = array_pop($tsettings['oldtstatus']);
                                    } else {
                                        $oldtstatus = 0;
                                    }
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatetopic',
                                        array(
                                            'tid' => $topic['tid'],
                                            'tstatus' => $oldtstatus,
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                break;
                                case 'purge':
                                    // permanent delete
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'admin',
                                        'deletetopic',
                                        array(
                                            'tid' => $topic['tid'])
                                    )) {
                                        return;
                                    }
                                break;
                            }
                            $seenposters[$topic['towner']] = 1;
                        }
                        if (!empty($seenposters)) {
                            $seenposters = array_keys($seenposters);
                            foreach ($seenposters as $seenuid) {
                                // update user this topic belongs to
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'updateposter',
                                    array('uid' => $seenuid)
                                )) {
                                    return;
                                }
                            }
                        }
                        // re-synch forum
                        $lastpost = xarMod::apiFunc(
                            'crispbb',
                            'user',
                            'getposts',
                            array(
                                'fid' => $fid,
                                'tstatus' => array(0,1,2,4),
                                'pstatus' => array(0),
                                'sort' => 'ptime',
                                'order' => 'DESC',
                                'numitems' => 1,
                        )
                        );
                        $lastpost = !empty($lastpost) ? reset($lastpost) : array();
                        if (!xarMod::apiFunc(
                            'crispbb',
                            'admin',
                            'update',
                            array(
                                'fid' => $fid,
                                'lasttid' => !empty($lastpost['tid']) ? $lastpost['tid'] : 0,
                                'nohooks' => true
                            )
                        )) {
                            return;
                        }
                        if (empty($return_url)) {
                            $return_url = xarSession::getVar('crispbb_return_url');
                        }
                        xarSession::setVar('crispbb_return_url', '');
                        if (empty($return_url)) {
                            if (count($seentids) == 1 && $modaction != 'delete' && $modaction != 'purge') {
                                $return_url = xarModURL(
                                    'crispbb',
                                    'user',
                                    'display',
                                    array('tid' => $seentids[0])
                                );
                            } else {
                                $return_url = xarModURL(
                                    'crispbb',
                                    'user',
                                    'moderate',
                                    array('component' => 'topics', 'fid' => $fid, 'tstatus' => $tstatus)
                                );
                            }
                        }
                        return xarResponse::Redirect($return_url);
                    }
                }
                if (empty($return_url)) {
                    xarSession::setVar('crispbb_return_url', xarModURL(
                        'crispbb',
                        'user',
                        'moderate',
                        array('component' => 'topics', 'fid' => $fid, 'tstatus' => $tstatus)
                    ));
                }
                // pass data to form template
                $pageTitle = xarML('Moderate #(1) #(2)', $data['fname'], $component);
                $data['topics'] = xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'gettopics',
                    array(
                        'tstatus' => $tstatus,
                        'fid' => $fid,
                        'numitems' => $numitems,
                        'startnum' => $startnum,
                        'sort' => $sort,
                        'order' => strtoupper($order),
                        'numsubs' => true,
                        'numdels' => true,
                    )
                );
                $data['totaltopics'] = xarMod::apiFunc('crispbb', 'user', 'counttopics', array('tstatus' => $tstatus, 'fid' => $fid));
                $data['pager'] = xarTplGetPager(
                    $startnum,
                    $data['totaltopics'],
                    xarModURL('crispbb', 'user', 'moderate', array('component' => 'topics', 'fid' => $fid, 'tstatus' => $tstatus, 'startnum' => '%%', 'sort' => $sort, 'order' => $order)),
                    $numitems
                );
                $modactions = array();
                $check = array();
                $check['fid'] = $data['fid'];
                $check['catid'] = $data['catid'];
                $check['fstatus'] = $data['fstatus'];
                $check['fprivileges'] = $data['fprivileges'];
                $check['tstatus'] = 0;
                $check['towner'] = null;
                $tstatusoptions = $presets['tstatusoptions'];
                // topic closers
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'closetopics')
                )) {
                    if ($tstatus == 1) {
                        $modactions[] = array('id' => 'open', 'name' => xarML('Open'));
                    } elseif ($tstatus < 3) {
                        $modactions[] = array('id' => 'close', 'name' => xarML('Close'));
                    }
                } else {
                    unset($tstatusoptions[1]);
                }
                // topic approvers
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'approvetopics')
                )) {
                    if ($tstatus == 2) {
                        $modactions[] = array('id' => 'approve', 'name' => xarML('Approve'));
                    }
                } else {
                    unset($tstatusoptions[2]);
                }
                // topic movers
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'movetopics')
                )) {
                    if ($tstatus != 5 && $tstatus != 3) {
                        $modactions[] = array('id' => 'move', 'name' => xarML('Move'));
                    }
                } else {
                    unset($tstatusoptions[3]);
                }
                // topic lockers
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'locktopics')
                )) {
                    if ($tstatus == 4) {
                        $modactions[] = array('id' => 'unlock', 'name' => xarML('Unlock'));
                    } elseif ($tstatus != 5 && $tstatus != 3) {
                        $modactions[] = array('id' => 'lock', 'name' => xarML('Lock'));
                    }
                } else {
                    unset($tstatusoptions[4]);
                }
                // topic deleters
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'deletetopics')
                )) {
                    if ($tstatus == 5) {
                        $modactions[] = array('id' => 'undelete', 'name' => xarML('Un-delete'));
                    } else {
                        $modactions[] = array('id' => 'delete', 'name' => xarML('Delete'));
                    }
                } else {
                    unset($tstatusoptions[5]);
                }
                // forum editors
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'editforum')
                )) {
                    $modactions[] = array('id' => 'purge', 'name' => xarML('Purge'));
                }
                $data['modactions'] = $modactions;
                $data['tstatusoptions'] = $tstatusoptions;
                $data['forumoptions'] = $forumoptions;
                $data['tstatus'] = $tstatus;
            } else {
                if (!xarVar::fetch('movefid', 'id', $movefid, null, XARVAR_NOT_REQUIRED)) {
                    return;
                }
                if (!xarVar::fetch('movetid', 'id', $movetid, null, XARVAR_NOT_REQUIRED)) {
                    return;
                }
                if (!xarVar::fetch('mergetid', 'checkbox', $mergetid, false, XARVAR_NOT_REQUIRED)) {
                    return;
                }
                if (!xarVar::fetch('shadow', 'checkbox', $shadow, false, XARVAR_NOT_REQUIRED)) {
                    return;
                }
                $forumoptions = array();
                //$forumoptions[0] = array('id' => '0', 'name' => xarML('All Forums'));
                foreach ($forums as $fkey => $fval) {
                    if (empty($fval['privs']['movetopics'])) {
                        continue;
                    }
                    if (empty($fid)) {
                        $fid = $fkey;
                    }
                    $forumoptions[$fkey] = array('id' => $fkey, 'name' => $fval['fname']);
                }
                if (empty($forumoptions[$fid]) || (!empty($movefid) && empty($forumoptions[$movefid]))) {
                    return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
                }
                $data = $forums[$fid];
                $seentids = array();
                if (!empty($tids) && is_array($tids)) {
                    foreach ($tids as $seentid => $checked) {
                        if (empty($seentid) || empty($checked)) {
                            continue;
                        }
                        $seentids[$seentid] = 1;
                    }
                    $seentids = !empty($seentids) ? array_keys($seentids) : array();
                }
                if (!empty($seentids) && is_array($seentids)) {
                    $topics = xarMod::apiFunc(
                        'crispbb',
                        'user',
                        'gettopics',
                        array('tid' => $seentids)
                    );
                }
                if (empty($topics)) {
                    $msg = xarML('No topics selected for this action');
                    $ertype = 'NO_TOPICS';
                    $pageTitle = xarML('No topics selected');
                    $errorMsg['message'] = $msg;
                    $errorMsg['return_url'] = xarServer::getBaseURL();
                    $errorMsg['type'] = $ertype;
                    $errorMsg['pageTitle'] = $pageTitle;
                    xarTPLSetPageTitle(xarVar::prepForDisplay($errorMsg['pageTitle']));
                    return xarTPLModule('crispbb', 'user', 'error', $errorMsg);
                }

                if ($phase == 'update') {
                    // do validations for move
                    if ($mergetid) {
                        if (empty($movetid)) { // no topic to merge with
                            $invalid['mergetid'] = xarML('Select a topic to merge into');
                        } elseif (!empty($tids[$movetid])) { // merging with itself
                            $invalid['mergetid'] = xarML('Can\'t merge topic with itself');
                        }
                    } else {
                        if (empty($movefid)) { // no forum selected
                            $invalid['movefid'] = xarML('Select a forum to move topic(s) to');
                        } elseif ($movefid == $fid) { // same forum selected
                            $invalid['movefid'] = xarML('Select a forum to move topic(s) to');
                        }
                    }
                    $allowed = true;
                    // check permissions
                    if ($mergetid && !empty($movetid)) { // check against topic we're merging with
                        $checkt = xarMod::apiFunc('crispbb', 'user', 'gettopic', array('tid' => $movetid, 'privcheck' => true));
                        if ($checkt == 'NO_PRIVILEGES') {
                            $allowed = false;
                        } else {
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'checkseclevel',
                                array('check' => $checkt, 'priv' => 'movetopics')
                            )) {
                                $allowed = false;
                            }
                        }
                    } elseif (!empty($movefid) && $movefid != $fid) {
                        $checkf = !empty($forums[$movefid]) ? $forums[$movefid] : array();
                        if (empty($checkf)) {
                            $allowed = false;
                        } else {
                            $checkf['tstatus'] = null;
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'checkseclevel',
                                array('check' => $checkf, 'priv' => 'movetopics')
                            )) {
                                $allowed = false;
                            }
                        }
                    }

                    // a failure here generally means the user manually changed params somewhere
                    if (!$allowed) {
                        return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
                    }

                    if (empty($invalid)) {
                        // check for confirmation
                        if (!$confirm) {
                            // pass data to confirmation template
                            $data['topics'] = $topics;
                            $data['modaction'] = $modaction;
                            $data['movefid'] = $movefid;
                            $data['mergetid'] = $mergetid;
                            $data['shadow'] = $shadow;
                            $data['movetid'] = $movetid;
                            $data['targettid'] = !empty($checkt) ? $checkt : array();
                            $data['targetfid'] = !empty($checkf) ? $checkf : array();
                            $data['shadow'] = $shadow;
                            $data['component'] = $component;
                            $data['pageTitle'] = xarML('Confirm Action');
                            $data['return_url'] = $return_url;
                            return xarTPLModule('crispbb', 'user', 'moderate-confirm', $data);
                        }
                        // finally, perform requested action
                        if (!xarSecConfirmAuthKey()) {
                            return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'bad_author'));
                        }

                        if ($mergetid) {
                            // topic we're merging into
                            $target = $checkt;
                            // posts to merge, sorted oldest first
                            $newposts = xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'getposts',
                                array(
                                    'tid' => $seentids,
                                    'pstatus' => array(0,1),
                                    'sort' => 'ptime',
                                    'order' => 'ASC'
                                )
                            );
                            // can't have posts with times older than the topic start
                            $mintime = $target['ttime'];
                            foreach ($newposts as $newpid => $newpost) {
                                // reply posted before last reply time
                                if ($newpost['ptime'] <= $mintime) {
                                    // increment replytime by 1
                                    $mintime++;
                                    // update post time
                                    $newpost['ptime'] = $mintime;
                                }
                                if ($newpost['firstpid'] == $newpid) {
                                    $newpost['poststype'] = $newpost['topicstype'];
                                }
                                // move the post
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'updatepost',
                                    array(
                                        'pid' => $newpost['pid'],
                                        'tid' => $target['tid'],
                                        'ptime' => $newpost['ptime'],
                                        'poststype' => $newpost['poststype'],
                                        'nohooks' => true
                                    )
                                )) {
                                    return;
                                }
                                $mintime = $newpost['ptime'];
                            }
                            // get the lastpost for the topic we merged into
                            $lastpost = xarMod::apiFunc('crispbb', 'user', 'getposts', array('tid' => $target['tid'], 'sort' => 'ptime', 'order' => 'DESC', 'numitems' => 1));
                            $lastpost = !empty($lastpost) ? reset($lastpost) : array();
                            // update the lastpid
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'updatetopic',
                                array(
                                    'tid' => $lastpost['tid'],
                                    'lastpid' => $lastpost['pid'],
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            // remove the merged topics
                            foreach ($topics as $tid => $topic) {
                                // mark topic merged
                                $tsettings = $data['tsettings'];
                                $merged = array(
                                    'tid' => $target['tid'],
                                    'time' => $now,
                                    'by' => $uid,
                                    'nohooks' => true
                                    );
                                $tsettings['merged'][] = $merged;
                                // TODO: really these should be purged, need admin privs for that though
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'updatetopic',
                                    array(
                                        'tid' => $topic['tid'],
                                        'tstatus' => 9, // for now set an unused tstatus
                                        'tsettings' => $tsettings,
                                        'nohooks' => true
                                    )
                                )) {
                                    return;
                                }
                            }
                            // get the last reply in the forum topics were moved from
                            $lasttopic = xarMod::apiFunc('crispbb', 'user', 'getposts', array('fid' => $fid, 'numitems' => 1, 'sort' => 'ptime', 'order' => 'DESC', 'tstatus' => array(0,1,2,4), 'pstatus' => array(0,1)));
                            $lasttopic = !empty($lasttopic) ? reset($lasttopic) : array();
                            // update the forum the topics were moved from
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'admin',
                                'update',
                                array(
                                    'fid' => $fid,
                                    'lasttid' => !empty($lasttopic['tid']) ? $lasttopic['tid'] : 0,
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            // get the last reply in the forum topics were moved to
                            $lastreply = xarMod::apiFunc('crispbb', 'user', 'getposts', array('fid' => $target['fid'], 'numitems' => 1, 'sort' => 'ptime', 'order' => 'DESC', 'tstatus' => array(0,1,2,4), 'pstatus' => array(0,1)));
                            $lastreply = !empty($lastreply) ? reset($lastreply) : array();
                            // update the forum the topics were moved to
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'admin',
                                'update',
                                array(
                                    'fid' => $target['fid'],
                                    'lasttid' => !empty($lastreply['tid']) ? $lastreply['tid'] : 0,
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            if (empty($return_url)) {
                                $return_url = xarModURL(
                                    'crispbb',
                                    'user',
                                    'moderate',
                                    array(
                                        'component' => 'topics',
                                        'fid' => $fid,
                                    )
                                );
                            }
                            return xarResponse::Redirect($return_url);
                        } else {
                            // forum we're moving topics to
                            $target = $checkf;
                            foreach ($topics as $tid => $topic) {
                                $tsettings = $topic['tsettings'];
                                $moved = array(
                                    'tid' => $tid,
                                    'tofid' => $target['fid'],
                                    'fromfid' => $topic['fid'],
                                    'time' => $now,
                                    'by' => $uid
                                    );
                                $tsettings['moved'][] = $moved;
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'updatetopic',
                                    array(
                                        'tid' => $topic['tid'],
                                        'tsettings' => $tsettings,
                                        'fid' => $target['fid'],
                                        'nohooks' => true
                                    )
                                )) {
                                    return;
                                }
                                if ($shadow) {
                                    $item = array();
                                    $item['fid'] = $topic['fid'];
                                    $item['ttitle'] = xarML('Moved --- #(1)', $topic['ttitle']);
                                    $item['pdesc'] = $topic['pdesc'];
                                    $item['ptext'] = $topic['ptext'];
                                    $item['towner'] = $topic['towner'];
                                    $item['ttype'] = $topic['ttype'];
                                    $item['tstatus'] = 3; // moved
                                    $item['ptime'] = $topic['ptime'];
                                    $item['tsettings'] = $tsettings;
                                    if (!xarMod::apiFunc('crispbb', 'user', 'createtopic', $item)) {
                                        return;
                                    }
                                    // creating a shadow updates the forum last tid, this may be wrong
                                    // the forum update will rectify that, but we need to reset the tracker
                                    $fstring = xarModVars::get('crispbb', 'ftracking');
                                    $ftracking = (!empty($fstring)) ? unserialize($fstring) : array();
                                    $ftracking[$topic['fid']] = $tracker->lastUpdate($topic['fid']);
                                    xarModVars::set('crispbb', 'ftracking', serialize($ftracking));
                                }
                            }
                            // get the last reply in the forum topics were moved from
                            $lasttopic = xarMod::apiFunc('crispbb', 'user', 'getposts', array('fid' => $fid, 'numitems' => 1, 'sort' => 'ptime', 'order' => 'DESC', 'tstatus' => array(0,1,2,4), 'pstatus' => array(0,1)));
                            $lasttopic = !empty($lasttopic) ? reset($lasttopic) : array();
                            // update the forum the topics were moved from
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'admin',
                                'update',
                                array(
                                    'fid' => $fid,
                                    'lasttid' => !empty($lasttopic['tid']) ? $lasttopic['tid'] : 0,
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            // get the last reply in the forum topics were moved to
                            $lastreply = xarMod::apiFunc('crispbb', 'user', 'getposts', array('fid' => $target['fid'], 'numitems' => 1, 'sort' => 'ptime', 'order' => 'DESC', 'tstatus' => array(0,1,2,4), 'pstatus' => array(0,1)));
                            $lastreply = !empty($lastreply) ? reset($lastreply) : array();
                            // update the forum the topics were moved from
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'admin',
                                'update',
                                array(
                                    'fid' => $target['fid'],
                                    'lasttid' => !empty($lastreply['tid']) ? $lastreply['tid'] : 0,
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            if (empty($return_url)) {
                                $return_url = xarModURL(
                                    'crispbb',
                                    'user',
                                    'moderate',
                                    array(
                                        'component' => 'topics',
                                        'fid' => $fid,
                                    )
                                );
                            }
                            return xarResponse::Redirect($return_url);
                        }
                    }
                }
                // pass data to form template
                if ($mergetid) {
                    $data['ftopics'] = xarMod::apiFunc(
                        'crispbb',
                        'user',
                        'gettopics',
                        array(
                            'fid' => $movefid,
                            'numitems' => $numitems,
                            'startnum' => $startnum,
                            'tstatus' => array(0,1,2,4),
                            'sort' => $sort,
                            'order' => $order,
                        )
                    );
                    $data['fnumtopics'] = xarMod::apiFunc(
                        'crispbb',
                        'user',
                        'counttopics',
                        array(
                            'fid' => $movefid,
                            'tstatus' => array(0,1,2,4),
                        )
                    );
                    $data['pager'] = xarTplGetPager(
                        $startnum,
                        $data['fnumtopics'],
                        xarModURL('crispbb', 'user', 'moderate', array('component' => 'topics', 'fid' => $fid, 'modaction' => 'move', 'phase' => 'update', 'movefid' => $movefid, 'tids' => $tids, 'movetid' => $movetid, 'mergetid' => $mergetid, 'sort' => $sort, 'order' => $order, 'startnum' => '%%')),
                        $numitems
                    );
                }
                $pageTitle = xarML('Move Topic(s)');
                $data['topics'] = $topics;
                $data['shadow'] = $shadow;
                $data['forumoptions'] = $forumoptions;
                $data['movefid'] = empty($movefid) ? $fid : $movefid;
                $data['movetid'] = $movetid;
                $data['mergetid'] = $mergetid;
                $data['tids'] = $tids;
            }
        break;
        case 'posts':
            if (!xarVar::fetch('tid', 'id', $tid, null, XARVAR_NOT_REQUIRED)) {
                return;
            }
            if (!xarVar::fetch('pids', 'list', $pids, array(), XARVAR_NOT_REQUIRED)) {
                return;
            }
            if (!xarVar::fetch('pstatus', 'int', $pstatus, 0, XARVAR_NOT_REQUIRED)) {
                return;
            }
            if (!xarVar::fetch('layout', 'enum:list:replies', $layout, 'list', XARVAR_NOT_REQUIRED)) {
                return;
            }
            $data = xarMod::apiFunc(
                'crispbb',
                'user',
                'gettopic',
                array('tid' => $tid, 'privcheck' => true, 'numsubs' => true)
            );
            if (empty($data['modtopicurl'])) {
                $data = 'NO_PRIVILEGES';
            }
            if (!is_array($data)) {
                if ($data == 'BAD_DATA') {
                    return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'bad_author'));
                } else {
                    return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
                }
            }
            $forumoptions = array();
            //$forumoptions[0] = array('id' => '0', 'name' => xarML('All Forums'));
            foreach ($forums as $fkey => $fval) {
                if (empty($fval['modforumurl'])) {
                    continue;
                }
                $forumoptions[$fkey] = array('id' => $fkey, 'name' => $fval['fname']);
            }
            if (empty($forumoptions[$data['fid']])) {
                return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
            }

            if (empty($pids)) {
                $modaction = '';
            }
            if ($modaction != 'split') {
                if ($sort == 'ttime') {
                    $sort = 'ptime';
                }
                if ($phase == 'update') {
                    // do validations for current action
                    $seenpids = array();
                    if (!empty($pids) && is_array($pids)) {
                        foreach ($pids as $seenpid => $checked) {
                            if (empty($seenpid) || empty($checked)) {
                                continue;
                            }
                            $seenpids[$seenpid] = 1;
                        }
                        $seenpids = !empty($seenpids) ? array_keys($seenpids) : array();
                    }
                    if (empty($seenpids) || !is_array($seenpids)) {
                        $invalid['pids'] = xarML('No posts selected for this action');
                    }
                    $posts = xarMod::apiFunc(
                        'crispbb',
                        'user',
                        'getposts',
                        array('pid' => $seenpids, 'tid' => $tid)
                    );

                    if (empty($posts)) {
                        $invalid['pids'] = xarML('No posts found');
                    }

                    // take no chances here, devious users could have changed input manually
                    // so we check each post to make sure the user has adequate privs
                    $allowed = true;
                    foreach ($posts as $pcheck) {
                        switch ($modaction) {
                            case 'approve':
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'checkseclevel',
                                    array('check' => $pcheck, 'priv' => 'approvereplies')
                                )) {
                                    $allowed = false;
                                }
                            break;
                            case 'split':
                                    $allowed = false;
                            break;
                            case 'delete':
                            case 'undelete':
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'checkseclevel',
                                    array('check' => $pcheck, 'priv' => 'deletereplies')
                                )) {
                                    $allowed = false;
                                }
                            break;
                            case 'purge':
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'checkseclevel',
                                    array('check' => $pcheck, 'priv' => 'editforum')
                                )) {
                                    $allowed = false;
                                }
                            break;
                        }
                    }

                    // a failure here generally means the user manually changed params somewhere
                    if (!$allowed) {
                        return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
                    }

                    if (empty($invalid)) {
                        if (!$confirm) {
                            // pass data to confirmation template
                            $data['posts'] = $posts;
                            $data['modaction'] = $modaction;
                            $data['component'] = $component;
                            $data['pageTitle'] = xarML('Confirm Action');
                            $data['return_url'] = $return_url;
                            return xarTPLModule('crispbb', 'user', 'moderate-confirm', $data);
                        }
                        if (!xarSecConfirmAuthKey()) {
                            return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'bad_author'));
                        }
                        $seenposters = array();
                        // perform action on each post in turn
                        foreach ($posts as $pid => $post) {
                            switch ($modaction) {
                                case 'approve':
                                case 'undelete':
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatepost',
                                        array(
                                            'pid' => $post['pid'],
                                            'pstatus' => 0,
                                            'nohooks' => true
                                    )
                                    )) {
                                        return;
                                    }

                                    // ok to let subscribers know about this reply now
                                    if (xarMod::isAvailable('crispsubs') && $modaction == 'approve') {
                                        $topicstype = xarMod::apiFunc(
                                            'crispbb',
                                            'user',
                                            'getitemtype',
                                            array('fid' => $post['fid'], 'componenent' => 'topics')
                                        );
                                        if (xarModIsHooked('crispsubs', 'crispbb', $topicstype)) {
                                            xarMod::apiFunc(
                                                'crispsubs',
                                                'user',
                                                'updatehook',
                                                array(
                                                    'modname' => 'crispbb',
                                                    'itemtype' => $topicstype,
                                                    'objectid' => $post['tid'],
                                                    'objecturl' => $post['viewreplyurl']
                                                )
                                            );
                                        }
                                    }
                                break;
                                case 'delete':
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatepost',
                                        array(
                                            'pid' => $post['pid'],
                                            'pstatus' => 5,
                                            'nohooks' => true
                                    )
                                    )) {
                                        return;
                                    }
                                break;
                                case 'purge':
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'admin',
                                        'deletepost',
                                        array(
                                            'pid' => $post['pid']
                                    )
                                    )) {
                                        return;
                                    }
                                break;
                            }
                            $seenposters[$post['powner']] = 1;
                        }
                        if (!empty($seenposters)) {
                            $seenposters = array_keys($seenposters);
                            foreach ($seenposters as $seenuid) {
                                // update user this topic belongs to
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'updateposter',
                                    array('uid' => $seenuid)
                                )) {
                                    return;
                                }
                            }
                        }
                        // update the topic the posts came from
                        $lastreply = xarMod::apiFunc(
                            'crispbb',
                            'user',
                            'getposts',
                            array(
                                'tid' => $tid,
                                'sort' => 'ptime',
                                'order' => 'desc',
                                'tstatus' => array(0,1,2,4),
                                'pstatus' => 0,
                                'numitems' => 1
                        )
                        );
                        $lastreply = !empty($lastreply) ? reset($lastreply) : array();
                        if (!xarMod::apiFunc(
                            'crispbb',
                            'user',
                            'updatetopic',
                            array(
                                'tid' => $tid,
                                'lastpid' => $lastreply['pid'],
                                'nohooks' => true
                            )
                        )) {
                            return;
                        }
                        // update the from topic belongs to
                        $lasttopic = xarMod::apiFunc(
                            'crispbb',
                            'user',
                            'getposts',
                            array(
                                'fid' => $data['fid'],
                                'sort' => 'ptime',
                                'order' => 'desc',
                                'tstatus' => array(0,1,2,4),
                                'numitems' => 1,
                                'pstatus' => 0
                        )
                        );
                        $lasttopic = !empty($lasttopic) ? reset($lasttopic) : array();
                        if (!xarMod::apiFunc(
                            'crispbb',
                            'admin',
                            'update',
                            array(
                                'fid' => $data['fid'],
                                'lasttid' => !empty($lasttopic['tid']) ? $lasttopic['tid'] : 0,
                                'nohooks' => true
                            )
                        )) {
                            return;
                        }
                        if (empty($return_url)) {
                            $return_url = xarModURL('crispbb', 'user', 'display', array('tid' => $tid));
                        }
                        return xarResponse::Redirect($return_url);
                    }
                }
                $pstatuses = array(0);
                if (!empty($data['privs']['approvereplies'])) {
                    $pstatuses[] = 2;
                }
                if (!empty($data['privs']['deletereplies'])) {
                    $pstatuses[] = 5;
                }
                $posts = xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'getposts',
                    array(
                        'tid' => $tid,
                        'sort' => $sort,
                        'order' => $order,
                        'pstatus' => $pstatus,
                        'startnum' => $startnum,
                        'numitems' => $numitems
                    )
                );
                $seenposters = array();
                foreach ($posts as $apid => $apost) {
                    $item = $apost;
                    if (!empty($apost['towner'])) {
                        $seenposters[$apost['towner']] = 1;
                    }
                    if (!empty($apost['powner'])) {
                        $seenposters[$apost['powner']] = 1;
                    }
                    if ($apid == $apost['firstpid']) {
                        //unset($posts[$apid]);
                        //continue;
                    }
                    //$item['checked'] = (isset($pids[$apid]) || (!empty($startpid) && $startpid <= $apid && (empty($endpid) || $apid >= $endpid))) ? true : false;
                    $posts[$apid] = $item;
                }
                $data['posts'] = $posts;
                $uidlist = !empty($seenposters) ? array_keys($seenposters) : array();
                $posterlist = xarMod::apiFunc('roles', 'user', 'getall', array('uidlist' => $uidlist));
                $data['posterlist'] = $posterlist;
                $data['uidlist'] = $uidlist;
                $modactions = array();
                $check = $data;
                $pstatusoptions = $presets['pstatusoptions'];
                // topic approvers
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'approvereplies')
                )) {
                    if ($pstatus == 2) {
                        $modactions[] = array('id' => 'approve', 'name' => xarML('Approve'));
                    }
                } else {
                    unset($pstatusoptions[2]);
                }
                // topic splitters
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'splittopics')
                )) {
                    $modactions[] = array('id' => 'split', 'name' => xarML('Split'));
                } else {
                    unset($pstatusoptions[5]);
                }
                // post deleters
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'deletetopics')
                )) {
                    if ($pstatus != 5) {
                        $modactions[] = array('id' => 'delete', 'name' => xarML('Delete'));
                    } else {
                        $modactions[] = array('id' => 'undelete', 'name' => xarML('Un-delete'));
                    }
                } else {
                    unset($pstatusoptions[5]);
                }
                // forum editors
                if (xarMod::apiFunc(
                    'crispbb',
                    'user',
                    'checkseclevel',
                    array('check' => $check, 'priv' => 'editforum')
                )) {
                    $modactions[] = array('id' => 'purge', 'name' => xarML('Purge'));
                }
                $data['modactions'] = $modactions;
                $data['pstatusoptions'] = $pstatusoptions;
                $data['pstatus'] = $pstatus;
                $data['layout'] = $layout;
                $data['layouts'] = array(
                    array('id' => 'list', 'name' => xarML('List View')),
                    array('id' => 'replies', 'name' => xarML('Show Replies'))
                    );
                $data['psortfields'] = array(
                    array('id' => 'pid', 'name' => xarML('Post Id')),
                    array('id' => 'ptime', 'name' => xarML('Post Time')),
                    array('id' => 'powner', 'name' => xarML('Poster Name'))
                    );
                $data['sortorders'] = $presets['sortorderoptions'];
                $data['totalposts'] = xarMod::apiFunc('crispbb', 'user', 'countposts', array('pstatus' => $pstatus, 'tid' => $tid));
                $data['pager'] = xarTplGetPager(
                    $startnum,
                    $data['totalposts'],
                    xarModURL('crispbb', 'user', 'moderate', array('component' => 'posts', 'tid' => $tid, 'pstatus' => $pstatus, 'startnum' => '%%', 'sort' => $sort, 'order' => $order)),
                    $numitems
                );
            } else {
                if (!xarVar::fetch('movefid', 'id', $movefid, null, XARVAR_NOT_REQUIRED)) {
                    return;
                }
                if (!xarVar::fetch('movetid', 'id', $movetid, null, XARVAR_NOT_REQUIRED)) {
                    return;
                }
                if (!xarVar::fetch('mergetid', 'checkbox', $mergetid, false, XARVAR_NOT_REQUIRED)) {
                    return;
                }
                if (!xarVar::fetch('ttitle', 'str:1:255', $ttitle, '', XARVAR_NOT_REQUIRED)) {
                    return;
                }
                $forumoptions = array();
                //$forumoptions[0] = array('id' => '0', 'name' => xarML('All Forums'));
                foreach ($forums as $fkey => $fval) {
                    if (empty($mergetid)) {
                        if (empty($fval['privs']['newtopic']) || empty($fval['privs']['splittopics'])) {
                            continue;
                        }
                    } else {
                        if (empty($fval['privs']['splittopics'])) {
                            continue;
                        }
                    }
                    $forumoptions[$fkey] = array('id' => $fkey, 'name' => $fval['fname']);
                }
                if (empty($forumoptions[$data['fid']]) || (!empty($movefid) && empty($forumoptions[$movefid]))) {
                    return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
                }

                $seenpids = array();
                if (!empty($pids) && is_array($pids)) {
                    foreach ($pids as $seenpid => $checked) {
                        if (empty($seenpid) || empty($checked)) {
                            continue;
                        }
                        $seenpids[$seenpid] = 1;
                    }
                    $seenpids = !empty($seenpids) ? array_keys($seenpids) : array();
                }

                if (!empty($seenpids) && is_array($seenpids)) {
                    $posts = xarMod::apiFunc(
                        'crispbb',
                        'user',
                        'getposts',
                        array('pid' => $seenpids, 'tid' => $tid)
                    );
                }

                if (empty($posts)) {
                    $msg = xarML('No posts selected for this action');
                    $ertype = 'NO_POSTS';
                    $pageTitle = xarML('No posts selected');
                    $errorMsg['message'] = $msg;
                    $errorMsg['return_url'] = xarServer::getBaseURL();
                    $errorMsg['type'] = $ertype;
                    $errorMsg['pageTitle'] = $pageTitle;
                    xarTPLSetPageTitle(xarVar::prepForDisplay($errorMsg['pageTitle']));
                    return xarTPLModule('crispbb', 'user', 'error', $errorMsg);
                }

                if ($phase == 'update') {
                    // do validations for split
                    if ($mergetid) {
                        if (empty($movetid)) { // no topic to merge with
                            $invalid['mergetid'] = xarML('Select a topic to merge into');
                        } elseif ($movetid == $tid) {
                            $invalid['mergetid'] = xarML('Posts are already in selected topic');
                        }
                    } else {
                        if (empty($movefid)) { // no forum selected
                            $invalid['movefid'] = xarML('Select a forum');
                        } else {
                            if (empty($ttitle) || !is_string($ttitle) || strlen($ttitle) > $forums[$movefid]['topictitlemax'] || strlen($ttitle) < $forums[$movefid]['topictitlemin']) {
                                $invalid['ttitle'] = xarML('Topic title must be between #(1) and #(2) characters', $forums[$movefid]['topictitlemin'], $forums[$movefid]['topictitlemax']);
                            }
                        }
                    }

                    $allowed = true;
                    // check permissions
                    if ($mergetid && !empty($movetid)) { // check against topic we're merging with
                        $checkt = xarMod::apiFunc('crispbb', 'user', 'gettopic', array('tid' => $movetid, 'privcheck' => true));
                        if ($checkt == 'NO_PRIVILEGES') {
                            $allowed = false;
                        } else {
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'checkseclevel',
                                array('check' => $checkt, 'priv' => 'movetopics')
                            )) {
                                $allowed = false;
                            }
                        }
                    } elseif (!empty($movefid)) {
                        $checkf = !empty($forums[$movefid]) ? $forums[$movefid] : array();
                        if (empty($checkf)) {
                            $allowed = false;
                        } else {
                            $checkf['tstatus'] = null;
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'checkseclevel',
                                array('check' => $checkf, 'priv' => 'movetopics')
                            )) {
                                $allowed = false;
                            }
                        }
                    }

                    // a failure here generally means the user manually changed params somewhere
                    if (!$allowed) {
                        return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'no_privileges'));
                    }
                    if (empty($invalid)) {
                        if (!$confirm) {
                            // pass data to confirmation template
                            $data['posts'] = $posts;
                            $data['modaction'] = $modaction;
                            $data['movefid'] = $movefid;
                            $data['mergetid'] = $mergetid;
                            $data['movetid'] = $movetid;
                            $data['ttitle'] = $ttitle;
                            $data['targettid'] = !empty($checkt) ? $checkt : array();
                            $data['targetfid'] = !empty($checkf) ? $checkf : array();
                            $data['component'] = $component;
                            $data['pageTitle'] = xarML('Confirm Action');
                            $data['return_url'] = $return_url;
                            return xarTPLModule('crispbb', 'user', 'moderate-confirm', $data);
                        }
                        if (!xarSecConfirmAuthKey()) {
                            return xarTpl::module('privileges', 'user', 'errors', array('layout' => 'bad_author'));
                        }

                        if ($mergetid) {
                            // topic we're merging into
                            $target = $checkt;
                            // posts to merge, sorted oldest first
                            $newposts = xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'getposts',
                                array(
                                    'pid' => array_keys($posts),
                                    'sort' => 'ptime',
                                    'order' => 'ASC'
                                )
                            );
                            // can't have posts with times older than the topic start
                            $mintime = $target['ttime'];
                            foreach ($newposts as $newpid => $newpost) {
                                // reply posted before last reply time
                                if ($newpost['ptime'] <= $mintime) {
                                    // increment replytime by 1
                                    $mintime++;
                                    // update post time
                                    $newpost['ptime'] = $mintime;
                                }
                                if ($newpost['firstpid'] == $newpid) {
                                    $newpost['poststype'] = $newpost['topicstype'];
                                }
                                // move the post
                                if (!xarMod::apiFunc(
                                    'crispbb',
                                    'user',
                                    'updatepost',
                                    array(
                                        'pid' => $newpost['pid'],
                                        'tid' => $target['tid'],
                                        'ptime' => $newpost['ptime'],
                                        'poststype' => $newpost['poststype'],
                                        'nohooks' => true
                                    )
                                )) {
                                    return;
                                }
                                $mintime = $newpost['ptime'];
                            }
                            // update the topic that posts were moved from
                            $lastreply = xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'getposts',
                                array(
                                    'tid' => $tid,
                                    'numitems' => 1,
                                    'sort' => 'ptime',
                                    'order' => 'DESC'
                                )
                            );
                            $lastreply = !empty($lastreply) ? reset($lastreply) : array();
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'updatetopic',
                                array(
                                    'tid' => $tid,
                                    'lastpid' => $lastreply['pid'],
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            unset($lastreply);
                            // update the forum that posts were moved from
                            $lasttopic = xarMod::apiFunc('crispbb', 'user', 'getposts', array('fid' => $data['fid'], 'numitems' => 1, 'sort' => 'ptime', 'order' => 'DESC', 'tstatus' => array(0,1,2,4), 'pstatus' => array(0,1)));
                            $lasttopic = !empty($lasttopic) ? reset($lasttopic) : array();
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'admin',
                                'update',
                                array(
                                    'fid' => $data['fid'],
                                    'lasttid' => !empty($lasttopic['tid']) ? $lasttopic['tid'] : 0,
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            unset($lasttopic);
                            // update the topic that posts were moved to
                            $lastreply = xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'getposts',
                                array(
                                    'tid' => $target['tid'],
                                    'numitems' => 1,
                                    'sort' => 'ptime',
                                    'order' => 'DESC'
                                )
                            );
                            $lastreply = !empty($lastreply) ? reset($lastreply) : array();
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'updatetopic',
                                array(
                                    'tid' => $target['tid'],
                                    'lastpid' => $lastreply['pid'],
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            unset($lastreply);
                            // update the forum that posts were moved to
                            $lasttopic = xarMod::apiFunc('crispbb', 'user', 'getposts', array('fid' => $target['fid'], 'numitems' => 1, 'sort' => 'ptime', 'order' => 'DESC', 'tstatus' => array(0,1,2,4), 'pstatus' => array(0,1)));
                            $lasttopic = !empty($lasttopic) ? reset($lasttopic) : array();
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'admin',
                                'update',
                                array(
                                    'fid' => $target['fid'],
                                    'lasttid' => !empty($lasttopic['tid']) ? $lasttopic['tid'] : 0,
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            unset($lasttopic);
                        } else {
                            $target = $checkf;
                            $created = false;
                            // posts to split, sorted oldest first
                            $newposts = xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'getposts',
                                array(
                                    'pid' => array_keys($posts),
                                    'sort' => 'ptime',
                                    'order' => 'ASC'
                                )
                            );
                            // create the new topic in the selected forum
                            foreach ($newposts as $newpid => $newpost) {
                                if (!$created) { // first post is the new topic
                                    if (!$newtid = xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'createtopic',
                                        array(
                                            'ttitle' => $ttitle,
                                            'tstatus' => 0,
                                            'tsettings' => $data['tsettings'],
                                            'towner' => $newpost['powner'],
                                            'firstpid' => $newpid,
                                            'fid' => $target['fid'],
                                            'ttype' => 0
                                        )
                                    )) {
                                        return;
                                    }
                                    $created = true;
                                } else { // subsequent replies
                                    if (!xarMod::apiFunc(
                                        'crispbb',
                                        'user',
                                        'updatepost',
                                        array(
                                            'pid' => $newpid,
                                            'tid' => $newtid,
                                            'nohooks' => true
                                        )
                                    )) {
                                        return;
                                    }
                                }
                                $lastpid = $newpid; // keep track of the last post id
                            }
                            // update last post id for the new topic
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'updatetopic',
                                array(
                                    'tid' => $newtid,
                                    'lastpid' => $lastpid,
                                    'nohooks' => true
                               )
                            )) {
                                return;
                            }
                            // update last topic id for the forum topic was created in
                            $lasttopic = xarMod::apiFunc('crispbb', 'user', 'getposts', array('fid' => $target['fid'], 'numitems' => 1, 'sort' => 'ptime', 'order' => 'DESC', 'tstatus' => array(0,1,2,4), 'pstatus' => array(0,1)));
                            $lasttopic = !empty($lasttopic) ? reset($lasttopic) : array();
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'admin',
                                'update',
                                array(
                                    'fid' => $target['fid'],
                                    'lasttid' => !empty($lasttopic['tid']) ? $lasttopic['tid'] : 0,
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            unset($lasttopic);
                            // update last post pid for the topic posts were split from
                            $lastreply = xarMod::apiFunc('crispbb', 'user', 'getposts', array('tid' => $tid, 'sort' => 'ptime', 'order' => 'DESC', 'numitems' => 1));
                            $lastreply = !empty($lastreply) ? reset($lastreply) : array();
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'user',
                                'updatetopic',
                                array(
                                    'tid' => $tid,
                                    'lastpid' => $lastreply['pid'],
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            unset($lastreply);
                            // update the forum that posts were moved from
                            $lasttopic = xarMod::apiFunc('crispbb', 'user', 'getposts', array('fid' => $data['fid'], 'numitems' => 1, 'sort' => 'ptime', 'order' => 'DESC', 'tstatus' => array(0,1,2,4), 'pstatus' => array(0,1)));
                            $lasttopic = !empty($lasttopic) ? reset($lasttopic) : array();
                            if (!xarMod::apiFunc(
                                'crispbb',
                                'admin',
                                'update',
                                array(
                                    'fid' => $data['fid'],
                                    'lasttid' => !empty($lasttopic['tid']) ? $lasttopic['tid'] : 0,
                                    'nohooks' => true
                                )
                            )) {
                                return;
                            }
                            unset($lasttopic);
                        }
                        if (empty($return_url)) {
                            if (!empty($newtid)) {
                                $return_url = xarModURL('crispbb', 'user', 'display', array('tid' => $newtid));
                            } else {
                                $return_url = xarModURL('crispbb', 'user', 'display', array('tid' => $tid));
                            }
                        }
                        return xarResponse::Redirect($return_url);
                    }
                }

                $data['posts'] = $posts;
                $data['mergetid'] = $mergetid;
                $data['movetid'] = $movetid;
                $data['movefid'] = $movefid;
                $data['ttitle'] = empty($mergetid) ? $ttitle : '';
                $data['forumoptions'] = $forumoptions;
                $data['pids'] = $pids;
                if ($mergetid) {
                    $numitems = 10;
                    $data['ftopics'] = xarMod::apiFunc(
                        'crispbb',
                        'user',
                        'gettopics',
                        array(
                            'fid' => $movefid,
                            'numitems' => $numitems,
                            'startnum' => $startnum,
                            'tstatus' => array(0,1,2,4),
                            'sort' => $sort,
                            'order' => $order,
                        )
                    );
                    $data['fnumtopics'] = xarMod::apiFunc(
                        'crispbb',
                        'user',
                        'counttopics',
                        array(
                            'fid' => $movefid,
                            'tstatus' => array(0,1,2,4),
                        )
                    );
                    $data['pager'] = xarTplGetPager(
                        $startnum,
                        $data['fnumtopics'],
                        xarModURL('crispbb', 'user', 'moderate', array('component' => 'posts', 'tid' => $tid, 'modaction' => 'split', 'phase' => 'update', 'movefid' => $movefid, 'pids' => $pids, 'movetid' => $movetid, 'mergetid' => $mergetid, 'sort' => $sort, 'order' => $order, 'startnum' => '%%')),
                        $numitems
                    );
                }
            }

            $pageTitle = xarML('Moderate #(1) #(2)', $data['ttitle'], $component);
        break;
    }

    $data['userpanel'] = $tracker->getUserPanelInfo();

    $data['invalid'] = $invalid;
    $data['pageTitle'] = $pageTitle;
    $data['component'] = $component;
    $data['modaction'] = $modaction;
    $data['startnum'] = $startnum;
    $data['sort'] = $sort;
    $data['order'] = $order;
    $data['return_url'] = $return_url;

    xarTpl::setPageTitle(xarVar::prepForDisplay($pageTitle));



    return $data;
}
