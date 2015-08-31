<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 * File for the CiviCRM APIv3 pledge functions
 *
 * @package CiviCRM_APIv3
 * @subpackage API_Pledge
 *
 * @copyright CiviCRM LLC (c) 2004-2013
 * @version $Id: Pledge.php 2011-02-16 ErikHommel $
 */

/**
 * Add an Pledge for a contact
 *
 * Allowed @params array keys are:
 *
 * @example PledgeCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields pledge_create}
 * @access public
 */
function civicrm_api3_pledge_createbasic($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_pledge_createbasic_spec(&$params) {
  // TODO a 'clever' default should be introduced
  $params['is_primary']['api.default'] = 0;
  $params['pledge']['api.required'] = 1;
  $params['contact_id']['api.required'] = 1;
}

