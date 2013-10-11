<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                              |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
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
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */
                           
class GiftAid_Utils_CreateFDF {

    static function CreateFDF($file,$info){
        $data="%FDF-1.2\n%âãÏÓ\n1 0 obj\n<< \n/FDF << /Fields [ ";
        foreach($info as $field => $val){
        	if(is_array($val)){
            	$data.='<</T('.$field.')/V[';
            	foreach($val as $opt)
            		$data.='('.trim($opt).')';
            	$data.=']>>';
        	}else{
            	$data.='<</T('.$field.')/V('.trim($val).')>>';
        	}
        }
        $data.="] \n/F (".$file.") /ID [ <".md5(time()).">\n] >>".
            " \n>> \nendobj\ntrailer\n".
            "<<\n/Root 1 0 R \n\n>>\n%%EOF\n";
        return $data;
    }
    
}

