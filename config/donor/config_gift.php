<?php

/*
<LICENSE>

This file is part of AGENCY.

AGENCY is Copyright (c) 2003-2009 by Ken Tanzer and Downtown Emergency
Service Center (DESC).

All rights reserved.

For more information about AGENCY, see http://agency.sourceforge.net/
For more information about DESC, see http://www.desc.org/.

AGENCY is free software: you can redistribute it and/or modify
it under the terms of version 3 of the GNU General Public License
as published by the Free Software Foundation.

AGENCY is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with CHASERS.  If not, see <http://www.gnu.org/licenses/>.

For additional information, see the README.copyright file that
should be included in this distribution.

</LICENSE>
*/

$engine['gift']=array(
			    'allow_edit'=>false,
			    'allow_add'=>false,
			    'allow_delete'=>false,
			    'object_union' => array('gift_inkind','gift_cash'),
			    'list_fields'=>array('gift_date','gift_form','gift_amount','response_code','restriction_code','gift_comment'),
			    'fields'=>array(
						  'gift_id'=>array(
									 'data_type'=>'table_switch',
									 'table_switch'=>array(
												     'identifier'=>'::'
												     ),
									 'label' => '&nbsp;'
									 ),
						  'gift_amount'=>array('data_type'=>'currency')
						  )
			    );


?>
