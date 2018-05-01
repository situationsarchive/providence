<?php
/** ---------------------------------------------------------------------
 * app/lib/RelatableModelTrait.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2018 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * @package CollectiveAccess
 * @subpackage BaseModel
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */
 
trait RelatableModelTrait {
		# --------------------------------------------------------------------------------------------
	/**
	 * Creates a relationship between the currently loaded row and the specified row.
	 *
	 * @param mixed $pm_rel_table_name_or_num Table name (eg. "ca_entities") or number as defined in datamodel.conf of table containing row to creation relationship to.
	 * @param int $pn_rel_id primary key value of row to creation relationship to.
	 * @param mixed $pm_type_id Relationship type type_code or type_id, as defined in the ca_relationship_types table. This is required for all relationships that use relationship types. This includes all of the most common types of relationships.
	 * @param string $ps_effective_date Optional date expression to qualify relation with. Any expression that the TimeExpressionParser can handle is supported here.
	 * @param string $ps_source_info Text field for storing information about source of relationship. Not currently used.
	 * @param string $ps_direction Optional direction specification for self-relationships (relationships linking two rows in the same table). Valid values are 'ltor' (left-to-right) and  'rtol' (right-to-left); the direction determines which "side" of the relationship the currently loaded row is on: 'ltor' puts the current row on the left
     * @param null $pn_rank
side. For many self-relations the direction determines the nature and display text for the relationship.
	 * @param array $pa_options Array of additional options:
	 *		allowDuplicates = if set to true, attempts to add a relationship that already exists will succeed. Default is false – duplicate relationships will not be created
	 *		setErrorOnDuplicate = if set to true, an error will be set if an attempt is made to add a duplicate relationship. Default is false – don't set error. addRelationship() will always return false when creation of a duplicate relationship fails, no matter how the setErrorOnDuplicate option is set.
	 * @return bool|BaseRelationshipModel Loaded relationship model instance on success, false on error.
	 */
	public function addRelationship($pm_rel_table_name_or_num, $pn_rel_id, $pm_type_id=null, $ps_effective_date=null, $ps_source_info=null, $ps_direction=null, $pn_rank=null, $pa_options=null) {

		self::$_APPLICATION_PLUGIN_MANAGER->hookAddRelationship(array(
			'table_name' => $this->tableName(), 
			'instance' => &$this,
			'related_table' => &$pm_rel_table_name_or_num,
			'rel_id' => &$pn_rel_id,
			'type_id' => &$pm_type_id,
			'edate' => &$ps_effective_date,
			'source_info' => &$ps_source_info,
			'direction' => &$ps_direction,
			'rank' => &$pn_rank,
			'options' => &$pa_options,
		));

		if(is_null($ps_effective_date) && is_array($pa_options) && is_array($pa_options['interstitialValues'])) {
			$ps_effective_date = caGetOption('effective_date', $pa_options['interstitialValues'], null);
			unset($pa_options['interstitialValues']['effective_date']);
		}

		if(is_null($ps_source_info) && is_array($pa_options) && is_array($pa_options['interstitialValues'])) {
			$ps_source_info = caGetOption('source_info', $pa_options['interstitialValues'], null);
			unset($pa_options['interstitialValues']['source_info']);
		}

		if ($t_rel = $this->_addRelationship($pm_rel_table_name_or_num, $pn_rel_id, $pm_type_id, $ps_effective_date, $ps_source_info, $ps_direction, $pn_rank, $pa_options)) {
			if ($t_rel->numErrors()) {
				$this->errors = $t_rel->errors;
				return false;
			}
			$this->_processInterstitials($pa_options, $t_rel, false);
			if ($t_rel->numErrors()) {
				$this->errors = $t_rel->errors;
				return false;
			}
		}
		return $t_rel;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Edits the data in an existing relationship between the currently loaded row and the specified row.
	 *
	 * @param mixed $pm_rel_table_name_or_num Table name (eg. "ca_entities") or number as defined in datamodel.conf of table containing row to create relationships to.
	 * @param int $pn_relation_id primary key value of the relation to edit.
	 * @param int $pn_rel_id primary key value of row to creation relationship to.
	 * @param mixed $pm_type_id Relationship type type_code or type_id, as defined in the ca_relationship_types table. This is required for all relationships that use relationship types. This includes all of the most common types of relationships.
	 * @param string $ps_effective_date Optional date expression to qualify relation with. Any expression that the TimeExpressionParser can handle is supported here.
	 * @param mixed $pa_source_info Array or text for storing information about source of relationship. Not currently used.
	 * @param string $ps_direction Optional direction specification for self-relationships (relationships linking two rows in the same table). Valid values are 'ltor' (left-to-right) and  'rtol' (right-to-left); the direction determines which "side" of the relationship the currently loaded row is on: 'ltor' puts the current row on the left side. For many self-relations the direction determines the nature and display text for the relationship.
	 * @param null|int $pn_rank
	 * @param array $pa_options Array of additional options:
	 *		allowDuplicates = if set to true, attempts to edit a relationship to match one that already exists will succeed. Default is false – duplicate relationships will not be created.
	 *		setErrorOnDuplicate = if set to true, an error will be set if an attempt is made to create a duplicate relationship. Default is false – don't set error. editRelationship() will always return false when editing of a relationship fails, no matter how the setErrorOnDuplicate option is set.
	 * @return BaseRelationshipModel Loaded relationship model instance on success, false on error.
	 */
	public function editRelationship($pm_rel_table_name_or_num, $pn_relation_id, $pn_rel_id, $pm_type_id=null, $ps_effective_date=null, $pa_source_info=null, $ps_direction=null, $pn_rank=null, $pa_options=null) {
		if ($t_rel = $this->_editRelationship($pm_rel_table_name_or_num, $pn_relation_id, $pn_rel_id, $pm_type_id, $ps_effective_date, $pa_source_info, $ps_direction, $pn_rank, $pa_options)) {
			if ($t_rel->numErrors()) {
				$this->errors = $t_rel->errors;
				return false;
			}
			$this->_processInterstitials($pa_options, $t_rel, true);
			if ($t_rel->numErrors()) {
				$this->errors = $t_rel->errors;
				return false;
			}
		}
		return $t_rel;
	}
	# --------------------------------------------------------------------------------------------
	public function moveRelationships($pm_rel_table_name_or_num, $pn_to_id, $pa_options=null) {
		$vb_we_set_transaction = false;

		if (!$this->inTransaction()) {
			$this->setTransaction(new Transaction($this->getDb()));
			$vb_we_set_transaction = true;
		}

		self::$_APPLICATION_PLUGIN_MANAGER->hookBeforeMoveRelationships(array(
			'table_name' => $this->tableName(),
			'instance' => &$this,
			'related_table' => &$pm_rel_table_name_or_num,
			'to_id' => &$pn_to_id,
			'options' => &$pa_options,
		));

		$vn_rc = $this->_moveRelationships($pm_rel_table_name_or_num, $pn_to_id, $pa_options=null);

		self::$_APPLICATION_PLUGIN_MANAGER->hookAfterMoveRelationships(array(
			'table_name' => $this->tableName(),
			'instance' => &$this,
			'related_table' => &$pm_rel_table_name_or_num,
			'to_id' => &$pn_to_id,
			'options' => &$pa_options,
		));

		if ($this->numErrors()) {
			if ($vb_we_set_transaction) { $this->removeTransaction(false); }
			return false;
		} else {
			if ($vb_we_set_transaction) { $this->removeTransaction(true); }
		}

		return $vn_rc;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Creates a relationship between the currently loaded row and the specified row.
	 *
	 * @param mixed $pm_rel_table_name_or_num Table name (eg. "ca_entities") or number as defined in datamodel.conf of table containing row to creation relationship to.
	 * @param int $pn_rel_id primary key value of row to creation relationship to.
	 * @param mixed $pm_type_id Relationship type type_code or type_id, as defined in the ca_relationship_types table. This is required for all relationships that use relationship types. This includes all of the most common types of relationships.
	 * @param string $ps_effective_date Optional date expression to qualify relation with. Any expression that the TimeExpressionParser can handle is supported here.
	 * @param string $ps_source_info Text field for storing information about source of relationship. Not currently used.
	 * @param string $ps_direction Optional direction specification for self-relationships (relationships linking two rows in the same table). Valid values are 'ltor' (left-to-right) and  'rtol' (right-to-left); the direction determines which "side" of the relationship the currently loaded row is on: 'ltor' puts the current row on the left side. For many self-relations the direction determines the nature and display text for the relationship.
	 * @param array $pa_options Array of additional options:
	 *		allowDuplicates = if set to true, attempts to add a relationship that already exists will succeed. Default is false - duplicate relationships will not be created.
	 *		setErrorOnDuplicate = if set to true, an error will be set if an attempt is made to add a duplicate relationship. Default is false - don't set error. addRelationship() will always return false when creation of a duplicate relationship fails, no matter how the setErrorOnDuplicate option is set.
	 * @return BaseRelationshipModel Loaded relationship model instance on success, false on error.
	 */
	public function _addRelationship($pm_rel_table_name_or_num, $pn_rel_id, $pm_type_id=null, $ps_effective_date=null, $ps_source_info=null, $ps_direction=null, $pn_rank=null, $pa_options=null) {
		if(!($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num))) { 
			$this->postError(1240, _t('Related table specification "%1" is not valid', $pm_rel_table_name_or_num), 'BaseModel->addRelationship()');
			return false; 
		}
		$t_item_rel = $va_rel_info['t_item_rel'];
		$t_item_rel->clear();
		if ($this->inTransaction()) { $o_trans = $this->getTransaction(); $t_item_rel->setTransaction($o_trans); }
		
		if ($pm_type_id && !is_numeric($pm_type_id)) {
			$t_rel_type = new ca_relationship_types();
			if ($vs_linking_table = $t_rel_type->getRelationshipTypeTable($this->tableName(), $t_item_rel->tableName())) {
				$pn_type_id = $t_rel_type->getRelationshipTypeID($vs_linking_table, $pm_type_id);
			} else {
				$this->postError(2510, _t('Type id "%1" is not valid', $pm_type_id), 'BaseModel->addRelationship()');
				return false;
			}
		} else {
			$pn_type_id = $pm_type_id;
		}
		
		if (!is_numeric($pn_rel_id)) {
			if ($t_rel_item = Datamodel::getInstanceByTableName($va_rel_info['related_table_name'], true)) {
				if ($this->inTransaction()) { $t_rel_item->setTransaction($this->getTransaction()); }
				if (($vs_idno_fld = $t_rel_item->getProperty('ID_NUMBERING_ID_FIELD')) && $t_rel_item->load(array($vs_idno_fld => $pn_rel_id))) {
					$pn_rel_id = $t_rel_item->getPrimaryKey();
				} elseif(!is_numeric($pn_rel_id)) {
					return false;
				}
			}
		}
		
		if ((!isset($pa_options['allowDuplicates']) || !$pa_options['allowDuplicates']) && !$this->getAppConfig()->get('allow_duplicate_relationships') && $this->relationshipExists($pm_rel_table_name_or_num, $pn_rel_id, $pm_type_id, $ps_effective_date, $ps_direction)) {
			if (isset($pa_options['setErrorOnDuplicate']) && $pa_options['setErrorOnDuplicate']) {
				$this->postError(1100, _t('Relationship already exists'), 'BaseModel->addRelationship', $t_rel_item->tableName());
			}
			return false;
		}

		if ($va_rel_info['related_table_name'] == $this->tableName()) {
			// is self relation
			
			// is self relationship
			if ($ps_direction == 'rtol') {
				$t_item_rel->set($t_item_rel->getRightTableFieldName(), $this->getPrimaryKey());
				$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $pn_rel_id);
			} else {
				// default is left-to-right
				$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $this->getPrimaryKey());
				$t_item_rel->set($t_item_rel->getRightTableFieldName(), $pn_rel_id);
			}
			$t_item_rel->set($t_item_rel->getTypeFieldName(), $pn_type_id);		// TODO: verify type_id based upon type_id's of each end of the relationship
			if(!is_null($ps_effective_date)){ $t_item_rel->set('effective_date', $ps_effective_date); }
			if(!is_null($ps_source_info)){ $t_item_rel->set("source_info",$ps_source_info); }
			$t_item_rel->insert();
			
			if ($t_item_rel->numErrors() > 0) {
				$this->errors = array_merge($this->getErrors(), $t_item_rel->getErrors());
				return false;
			}
			return $t_item_rel;
		} else {
			switch(sizeof($va_rel_info['path'])) {
				case 3:		// many-to-many relationship
					
					$vs_left_table = $t_item_rel->getLeftTableName();

					if ($this->tableName() == $vs_left_table) {
						// is lefty
						$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $this->getPrimaryKey());
						$t_item_rel->set($t_item_rel->getRightTableFieldName(), $pn_rel_id);
					} else {
						// is righty
						$t_item_rel->set($t_item_rel->getRightTableFieldName(), $this->getPrimaryKey());
						$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $pn_rel_id);
					}
					
					$t_item_rel->set('rank', $pn_rank);	
					$t_item_rel->set($t_item_rel->getTypeFieldName(), $pn_type_id);		// TODO: verify type_id based upon type_id's of each end of the relationship
					if(!is_null($ps_effective_date)){ $t_item_rel->set('effective_date', $ps_effective_date); }
					if(!is_null($ps_source_info)){ $t_item_rel->set("source_info",$ps_source_info); }
					$t_item_rel->insert();
					
					if ($t_item_rel->numErrors() > 0) {
						$this->errors = array_merge($this->getErrors(), $t_item_rel->getErrors());
						return false;
					}
					
					return $t_item_rel;
					break;
				case 2:		// many-to-one relationship
					if ($this->tableName() == $va_rel_info['rel_keys']['one_table']) {
						if ($t_item_rel->load($pn_rel_id)) {
							$t_item_rel->set($va_rel_info['rel_keys']['many_table_field'], $this->getPrimaryKey());
							$t_item_rel->update();
							
							if ($t_item_rel->numErrors() > 0) {
								$this->errors = array_merge($this->getErrors(), $t_item_rel->getErrors());
								return false;
							}
						} else {
							$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $this->getPrimaryKey());
							$t_item_rel->set($t_item_rel->getRightTableFieldName(), $pn_rel_id);
							$t_item_rel->set($t_item_rel->getTypeFieldName(), $pn_type_id);	
							$t_item_rel->insert();
							
							if ($t_item_rel->numErrors() > 0) {
								$this->errors = array_merge($this->getErrors(), $t_item_rel->getErrors());
								return false;
							}
						}
						return $t_item_rel;
					} else {
						$this->set($va_rel_info['rel_keys']['many_table_field'], $pn_rel_id);
						$this->update();
					
						if ($this->numErrors() > 0) {
							$this->errors = array_merge($this->getErrors(), $t_item_rel->getErrors());
							return false;
						}
						return $this;
					}
					break;
				default:
					$this->postError(280, _t('Could not find a path to the specified related table'), 'BaseModel->addRelationship', $t_rel_item->tableName());
					return false;
			}
		}
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Edits the data in an existing relationship between the currently loaded row and the specified row.
	 *
	 * @param mixed $pm_rel_table_name_or_num Table name (eg. "ca_entities") or number as defined in datamodel.conf of table containing row to create relationships to.
	 * @param int $pn_relation_id primary key value of the relation to edit.
	 * @param int $pn_rel_id primary key value of row to creation relationship to.
	 * @param mixed $pm_type_id Relationship type type_code or type_id, as defined in the ca_relationship_types table. This is required for all relationships that use relationship types. This includes all of the most common types of relationships.
	 * @param string $ps_effective_date Optional date expression to qualify relation with. Any expression that the TimeExpressionParser can handle is supported here.
	 * @param string $ps_source_info Text field for storing information about source of relationship. Not currently used.
	 * @param string $ps_direction Optional direction specification for self-relationships (relationships linking two rows in the same table). Valid values are 'ltor' (left-to-right) and  'rtol' (right-to-left); the direction determines which "side" of the relationship the currently loaded row is on: 'ltor' puts the current row on the left side. For many self-relations the direction determines the nature and display text for the relationship.
	 * @param array $pa_options Array of additional options:
	 *		allowDuplicates = if set to true, attempts to edit a relationship to match one that already exists will succeed. Default is false - duplicate relationships will not be created.
	 *		setErrorOnDuplicate = if set to true, an error will be set if an attempt is made to create a duplicate relationship. Default is false - don't set error. editRelationship() will always return false when editing of a relationship fails, no matter how the setErrorOnDuplicate option is set.
	 * @return BaseRelationshipModel Loaded relationship model instance on success, false on error.
	 */
	public function _editRelationship($pm_rel_table_name_or_num, $pn_relation_id, $pn_rel_id, $pm_type_id=null, $ps_effective_date=null, $pa_source_info=null, $ps_direction=null, $pn_rank=null, $pa_options=null) {
		if(!($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num))) { 
			$this->postError(1240, _t('Related table specification "%1" is not valid', $pm_rel_table_name_or_num), 'BaseModel->editRelationship()');
			return false; 
		}
		$t_item_rel = $va_rel_info['t_item_rel'];
		if ($this->inTransaction()) { $t_item_rel->setTransaction($this->getTransaction()); }
		
		if ($pm_type_id && !is_numeric($pm_type_id)) {
			$t_rel_type = new ca_relationship_types();
			if ($vs_linking_table = $t_rel_type->getRelationshipTypeTable($this->tableName(), $t_item_rel->tableName())) {
				$pn_type_id = $t_rel_type->getRelationshipTypeID($vs_linking_table, $pm_type_id);
			}
		} else {
			$pn_type_id = $pm_type_id;
		}
		
		if (!is_numeric($pn_rel_id)) {
			if ($t_rel_item = Datamodel::getInstanceByTableName($va_rel_info['related_table_name'], true)) {
				if ($this->inTransaction()) { $t_rel_item->setTransaction($this->getTransaction()); }
				if (($vs_idno_fld = $t_rel_item->getProperty('ID_NUMBERING_ID_FIELD')) && $t_rel_item->load(array($vs_idno_fld => $pn_rel_id))) {
					$pn_rel_id = $t_rel_item->getPrimaryKey();
				}
			}
		}
		
		if ((!isset($pa_options['allowDuplicates']) || !$pa_options['allowDuplicates']) && !$this->getAppConfig()->get('allow_duplicate_relationships') && $this->relationshipExists($pm_rel_table_name_or_num, $pn_rel_id, $pm_type_id, $ps_effective_date, $ps_direction, array('relation_id' => $pn_relation_id))) {
			if (isset($pa_options['setErrorOnDuplicate']) && $pa_options['setErrorOnDuplicate']) {
				$this->postError(1100, _t('Relationship already exists'), 'BaseModel->addRelationship', $t_rel_item->tableName());
			}
			return false;
		}
		
		if ($va_rel_info['related_table_name'] == $this->tableName()) {
			// is self relation
			if ($t_item_rel->load($pn_relation_id)) {
				
				if ($ps_direction == 'rtol') {
					$t_item_rel->set($t_item_rel->getRightTableFieldName(), $this->getPrimaryKey());
					$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $pn_rel_id);
				} else {
					// default is left-to-right
					$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $this->getPrimaryKey());
					$t_item_rel->set($t_item_rel->getRightTableFieldName(), $pn_rel_id);
				}
				$t_item_rel->set('rank', $pn_rank);	
				$t_item_rel->set($t_item_rel->getTypeFieldName(), $pn_type_id);		// TODO: verify type_id based upon type_id's of each end of the relationship
				if(!is_null($ps_effective_date)){ $t_item_rel->set('effective_date', $ps_effective_date); }
				if(!is_null($pa_source_info)){ $t_item_rel->set("source_info",$pa_source_info); }
				$t_item_rel->update();
				if ($t_item_rel->numErrors()) {
					$this->errors = $t_item_rel->errors;
					return false;
				}
				return $t_item_rel;
			}
		} else {
			switch(sizeof($va_rel_info['path'])) {
				case 3:		// many-to-many relationship
					if ($t_item_rel->load($pn_relation_id)) {
						$vs_left_table = $t_item_rel->getLeftTableName();
						$vs_right_table = $t_item_rel->getRightTableName();
						if ($this->tableName() == $vs_left_table) {
							// is lefty
							$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $this->getPrimaryKey());
							$t_item_rel->set($t_item_rel->getRightTableFieldName(), $pn_rel_id);
						} else {
							// is righty
							$t_item_rel->set($t_item_rel->getRightTableFieldName(), $this->getPrimaryKey());
							$t_item_rel->set($t_item_rel->getLeftTableFieldName(), $pn_rel_id);
						}
						
						$t_item_rel->set('rank', $pn_rank);	
						$t_item_rel->set($t_item_rel->getTypeFieldName(), $pn_type_id);		// TODO: verify type_id based upon type_id's of each end of the relationship
						if(!is_null($ps_effective_date)){ $t_item_rel->set('effective_date', $ps_effective_date); }
						if(!is_null($pa_source_info)){ $t_item_rel->set("source_info",$pa_source_info); }
						$t_item_rel->update();
						
						if ($t_item_rel->numErrors()) {
							$this->errors = $t_item_rel->errors;
							return false;
						}
						
						return $t_item_rel;
					}
				case 2:		// many-to-one relations
					if ($this->tableName() == $va_rel_info['rel_keys']['one_table']) {
						if ($t_item_rel->load($pn_relation_id)) {
							$t_item_rel->set($va_rel_info['rel_keys']['many_table_field'], $this->getPrimaryKey());
							$t_item_rel->update();
							
							if ($t_item_rel->numErrors()) {
								$this->errors = $t_item_rel->errors;
								return false;
							}
							return $t_item_rel;
						}
						
						if ($t_item_rel->load($pn_rel_id)) {
							$t_item_rel->set($va_rel_info['rel_keys']['many_table_field'], $this->getPrimaryKey());
							$t_item_rel->update();
							
							if ($t_item_rel->numErrors()) {
								$this->errors = $t_item_rel->errors;
								return false;
							}
							return $t_item_rel;
						}
					} else {
						$this->set($va_rel_info['rel_keys']['many_table_field'], $pn_rel_id);
						$this->update();
						
						if ($this->numErrors()) {
							return false;
						}
						return $this;
					}
					break;
				default:
					return false;
					break;
			}
		}
		
		return false;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Removes the specified relationship
	 *
	 * @param mixed $pm_rel_table_name_or_num Table name (eg. "ca_entities") or number as defined in datamodel.conf of table containing row to edit relationships to.
	 * @param int $pn_relation_id primary key value of the relation to remove.
	 *  @return boolean True on success, false on error.
	 */
	public function removeRelationship($pm_rel_table_name_or_num, $pn_relation_id) {
		if(!($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num))) { 
			$this->postError(1240, _t('Related table specification "%1" is not valid', $pm_rel_table_name_or_num), 'BaseModel->removeRelationship()');
			return false; 
		}
		$t_item_rel = $va_rel_info['t_item_rel'];
		if ($this->inTransaction()) { $t_item_rel->setTransaction($this->getTransaction()); }
		
		
		if ($va_rel_info['related_table_name'] == $this->tableName()) {
			if ($t_item_rel->load($pn_relation_id)) {
				$t_item_rel->delete();
				
				if ($t_item_rel->numErrors()) {
					$this->errors = $t_item_rel->errors;
					return false;
				}
				return true;
			}	
		} else {
			switch(sizeof($va_rel_info['path'])) {
				case 3:		// many-to-one relationship
					if ($t_item_rel->load($pn_relation_id)) {
						$t_item_rel->delete();
						
						if ($t_item_rel->numErrors()) {
							$this->errors = $t_item_rel->errors;
							return false;
						}
						return true;
					}	
				case 2:
					if ($this->tableName() == $va_rel_info['rel_keys']['one_table']) {
						if ($t_item_rel->load($pn_relation_id)) {
							$t_item_rel->set($va_rel_info['rel_keys']['many_table_field'], null);
							$t_item_rel->update();
							
							if ($t_item_rel->numErrors()) {
								$this->errors = $t_item_rel->errors;
								return false;
							}
						}
					} else {
						$this->set($va_rel_info['rel_keys']['many_table_field'], null);
						$this->update();
						
						if ($this->numErrors()) {
							return false;
						}
					}
					break;
				default:
					return false;
					break;
			}
		}
		
		return false;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Remove all relations with the specified table from the currently loaded row
	 *
	 * @param mixed $pm_rel_table_name_or_num Table name (eg. "ca_entities") or number as defined in datamodel.conf of table containing row to removes relationships to.
	 * @param mixed $pm_type_id If set to a relationship type code or numeric type_id, only relationships with the specified type are removed.
	 * @param array $pa_options Options include:
	 *		restrictToTypes = 
	 *
	 * @return boolean True on success, false on error
	 */
	public function removeRelationships($pm_rel_table_name_or_num, $pm_relationship_type_id=null, $pa_options=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		if(!($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num))) { return null; }
		$t_item_rel = $va_rel_info['t_item_rel'];
		if (!method_exists($t_item_rel, "isRelationship") || !$t_item_rel->isRelationship()){ return false; }
		$va_sql_params = array();
		
		$pa_relationship_type_ids = caMakeRelationshipTypeIDList($t_item_rel->tableName(), $pm_relationship_type_id);
		
		$vs_join_sql = '';
		$vs_type_limit_sql = '';
		if ($pa_type_ids = caGetOption('restrictToTypes', $pa_options, null)) {
			$pa_type_ids = caMakeTypeIDList($pm_rel_table_name_or_num, $pa_type_ids);
			if (is_array($pa_type_ids) && (sizeof($pa_type_ids) > 0)) {
				
				if ($t_item_rel->tableName() == $this->getSelfRelationTableName()) {
					$vs_join_sql = "INNER JOIN ".$this->tableName()." AS t1 ON t1.".$this->primaryKey()." = r.".$t_item_rel->getLeftTableFieldName()."\n".
									"INNER JOIN ".$this->tableName()." AS t2 ON t2.".$this->primaryKey()." = r.".$t_item_rel->getRightTableFieldName()."\n";
				
					$vs_type_limit_sql = " AND (t1.type_id IN (?) OR t2.type_id IN (?))";
					$va_sql_params[] = $pa_type_ids; $va_sql_params[] = $pa_type_ids;
				} else {
					$vs_target_table_name = ($t_item_rel->getLeftTableName() == $this->tableName()) ? $t_item_rel->getRightTableName()  : $t_item_rel->getLeftTableName() ;
					$vs_target_table_pk = Datamodel::primaryKey($vs_target_table_name);
					
					$vs_join_sql = "INNER JOIN {$vs_target_table_name} AS t ON t.{$vs_target_table_pk} = r.{$vs_target_table_pk}\n";
				
					$vs_type_limit_sql = " AND (t.type_id IN (?))";
					$va_sql_params[] = $pa_type_ids; 
				}
				
			}
		}
		
		$vs_relationship_type_limit_sql = '';
		if (is_array($pa_relationship_type_ids) && (sizeof($pa_relationship_type_ids) > 0)) {
			$vs_relationship_type_limit_sql = " AND r.type_id IN (?)";
			$va_sql_params[] = $pa_relationship_type_ids;
		}
		
		$o_db = $this->getDb();
		
		if ($t_item_rel->tableName() == $this->getSelfRelationTableName()) {
			array_unshift($va_sql_params, (int)$vn_row_id);
			array_unshift($va_sql_params, (int)$vn_row_id);
			$qr_res = $o_db->query("
				SELECT r.relation_id FROM ".$t_item_rel->tableName()." r
				{$vs_join_sql}
				WHERE (r.".$t_item_rel->getLeftTableFieldName()." = ? OR r.".$t_item_rel->getRightTableFieldName()." = ?)
					{$vs_type_limit_sql} {$vs_relationship_type_limit_sql}
			", $va_sql_params);
			
			while($qr_res->nextRow()) {
				if (!$this->removeRelationship($pm_rel_table_name_or_num, $qr_res->get('relation_id'))) { 
					return false;
				}
			}
		} else {
			array_unshift($va_sql_params, (int)$vn_row_id);
			$qr_res = $o_db->query("
				SELECT r.relation_id FROM ".$t_item_rel->tableName()." r
				{$vs_join_sql}
				WHERE r.".$this->primaryKey()." = ?
					{$vs_type_limit_sql} {$vs_relationship_type_limit_sql}
			", $va_sql_params);
			
			while($qr_res->nextRow()) {
				if (!$this->removeRelationship($pm_rel_table_name_or_num, $qr_res->get('relation_id'))) { 
					return false;
				}
			}
		}
		return true;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 *
	 */
	public function getRelationshipInstance($pm_rel_table_name_or_num) {
		if ($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num)) {
			return $va_rel_info['t_item_rel'];
		}
		return null;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 *
	 */
	public function getRelationshipTableName($pm_rel_table_name_or_num) {
		if ($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num)) {
			if ($va_rel_info['t_item_rel']) { return $va_rel_info['t_item_rel']->tableName(); }
		}
		return null;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Moves relationships from currently loaded row to another row specified by $pn_row_id. The existing relationship
	 * rows are simply re-pointed to the new row, so this is a relatively fast operation. Note that this method does not copy 
	 * relationships, it moves them. After the operation completes no relationships to the specified related table will exist for the current row.
	 *
	 * @param mixed $pm_rel_table_name_or_num The table name or number of the related table. Only relationships pointing to this table will be moved.
	 * @param int $pn_to_id The primary key value of the row to move the relationships to.
	 * @param array $pa_options Array of options. No options are currently supported.
	 *
	 * @return int Number of relationships moved, or null on error. Note that you should carefully test the return value for null-ness rather than false-ness, since zero is a valid return value in cases where no relationships need to be moved. 
	 */
	public function _moveRelationships($pm_rel_table_name_or_num, $pn_to_id, $pa_options=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		if(!($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num))) { return null; }
		$t_item_rel = $va_rel_info['t_item_rel'];	// linking table
		
		$o_db = $this->getDb();
		
		$vs_item_pk = $this->primaryKey();
		
		if (!($t_rel_item = Datamodel::getInstanceByTableName($va_rel_info['related_table_name']))) {	// related item
			return null;
		}
		
		$va_to_reindex_relations = array();
		if ($t_item_rel->tableName() == $this->getSelfRelationTableName()) {
			$qr_res = $o_db->query("
				SELECT * FROM ".$t_item_rel->tableName()." 
				WHERE ".$t_item_rel->getLeftTableFieldName()." = ? OR ".$t_item_rel->getRightTableFieldName()." = ?
			", (int)$vn_row_id, (int)$vn_row_id);
			if ($o_db->numErrors()) { $this->errors = $o_db->errors; return null; }
			
			while($qr_res->nextRow()) {
				$va_to_reindex_relations[(int)$qr_res->get('relation_id')] = $qr_res->getRow();	
			}
			if (!sizeof($va_to_reindex_relations)) { return 0; }
			
			$o_db->query("
				UPDATE IGNORE ".$t_item_rel->tableName()." SET ".$t_item_rel->getLeftTableFieldName()." = ? WHERE ".$t_item_rel->getLeftTableFieldName()." = ?
			", (int)$pn_to_id, (int)$vn_row_id);
			if ($o_db->numErrors()) { $this->errors = $o_db->errors; return null; }
			$o_db->query("
				UPDATE IGNORE ".$t_item_rel->tableName()." SET ".$t_item_rel->getRightTableFieldName()." = ? WHERE ".$t_item_rel->getRightTableFieldName()." = ?
			", (int)$pn_to_id, (int)$vn_row_id);
			if ($o_db->numErrors()) { $this->errors = $o_db->errors; return null; }
		} else {
		    if (sizeof($va_rel_info['path']) == 3) {
                $qr_res = $o_db->query("
                    SELECT * FROM ".$t_item_rel->tableName()." WHERE {$vs_item_pk} = ?
                ", (int)$vn_row_id);
                if ($o_db->numErrors()) { $this->errors = $o_db->errors; return null; }
            
                while($qr_res->nextRow()) {
                    $va_to_reindex_relations[(int)$qr_res->get('relation_id')] = $qr_res->getRow();
                }
                if (!sizeof($va_to_reindex_relations)) { return 0; }
            
                $o_db->query("
                    UPDATE IGNORE ".$t_item_rel->tableName()." SET {$vs_item_pk} = ? WHERE {$vs_item_pk} = ?
                ", (int)$pn_to_id, (int)$vn_row_id);
                if ($o_db->numErrors()) { $this->errors = $o_db->errors; return null; }
            
                if ($t_item_rel->hasField('is_primary')) { // make sure there's only one primary
                    $qr_res = $o_db->query("
                        SELECT * FROM ".$t_item_rel->tableName()." WHERE {$vs_item_pk} = ?
                    ", (int)$pn_to_id);
                
                    $vn_first_primary_relation_id = null;
                
                    $vs_rel_pk = $t_item_rel->primaryKey();
                    while($qr_res->nextRow()) {
                        if ($qr_res->get('is_primary')) {
                            $vn_first_primary_relation_id = (int)$qr_res->get($vs_rel_pk);
                            break;
                        }
                    }
                
                    if ($vn_first_primary_relation_id) {
                        $o_db->query("
                            UPDATE IGNORE ".$t_item_rel->tableName()." SET is_primary = 0 WHERE {$vs_rel_pk} <> ? AND {$vs_item_pk} = ?
                        ", array($vn_first_primary_relation_id, (int)$pn_to_id));
                    }
                }
            }
		}
		
		$vn_rel_table_num = $t_item_rel->tableNum();
		
		// Reindex modified relationships
		
		$o_indexer = $this->getSearchIndexer();
		foreach($va_to_reindex_relations as $vn_relation_id => $va_row) {
			$o_indexer->indexRow($vn_rel_table_num, $vn_relation_id, $va_row, false, null, array($vs_item_pk => true));
		}
		
		return sizeof($va_to_reindex_relations);
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Copies relationships from currently loaded row to another row specified by $pn_row_id. If you want to transfer relationships
	 * from one row to another use moveRelationships() which is much faster than copying and then deleting relationships.
	 *
	 * @see moveRelationships()
	 * 
	 * @param mixed $pm_rel_table_name_or_num The table name or number of the related table. Only relationships pointing to this table will be moved.
	 * @param int $pn_to_id The primary key value of the row to move the relationships to.
	 * @param array $pa_options Array of options. Options include:
	 *		copyAttributes = Copy metadata attributes associated with each relationship, if the calling model supports attributes. [Default is false]
	 *
	 * @return int Number of relationships copied, or null on error. Note that you should carefully test the return value for null-ness rather than false-ness, since zero is a valid return value in cases where no relationships were available to be copied. 
	 */
	public function copyRelationships($pm_rel_table_name_or_num, $pn_to_id, $pa_options=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		if(!($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num))) { return null; }
		$t_item_rel = $va_rel_info['t_item_rel'];	// linking table
		if ($this->inTransaction()) { $t_item_rel->setTransaction($this->getTransaction()); }
		
		$vb_copy_attributes = caGetOption('copyAttributes', $pa_options, false, array('castTo' => 'boolean')) && method_exists($this, 'copyAttributesFrom');
		
		$o_db = $this->getDb();
		
		$vs_item_pk = $this->primaryKey();
		
		if (!($t_rel_item = Datamodel::getInstanceByTableName($va_rel_info['related_table_name']))) {	// related item
			return null;
		}
		
		$va_to_reindex_relations = array();
		if ($t_item_rel->tableName() == $this->getSelfRelationTableName()) {
			$vs_left_field_name = $t_item_rel->getLeftTableFieldName();
			$vs_right_field_name = $t_item_rel->getRightTableFieldName();
			
			$qr_res = $o_db->query("
				SELECT * 
				FROM ".$t_item_rel->tableName()." 
				WHERE 
					({$vs_left_field_name} = ?) OR ({$vs_right_field_name} = ?)
			", (int)$vn_row_id, (int)$vn_row_id);
			if ($o_db->numErrors()) { $this->errors = $o_db->errors; return null; }
			
			while($qr_res->nextRow()) {
				$va_to_reindex_relations[(int)$qr_res->get('relation_id')] = $qr_res->getRow();	
			}
			if (!sizeof($va_to_reindex_relations)) { return 0; }
			
			$va_new_relations = array();
			foreach($va_to_reindex_relations as $vn_relation_id => $va_row) {
				$t_item_rel->clear();
				unset($va_row[$t_item_rel->primaryKey()]);
				
				if ($va_row[$vs_left_field_name] == $vn_row_id) {
					$va_row[$vs_left_field_name] = $pn_to_id;
				} else {
					$va_row[$vs_right_field_name] = $pn_to_id;
				}
				
				$t_item_rel->set($va_row);
				$t_item_rel->insert();
				if ($t_item_rel->numErrors()) {
					$this->errors = $t_item_rel->errors; return null;	
				}
				$va_new_relations[$t_item_rel->getPrimaryKey()] = $va_row;
	
				if ($vb_copy_attributes) {
					$t_item_rel->copyAttributesFrom($vn_relation_id);
					if ($t_item_rel->numErrors()) {
						$this->errors = $t_item_rel->errors; return null;	
					}
				}
			}
		} else {
			$qr_res = $o_db->query("
				SELECT * 
				FROM ".$t_item_rel->tableName()." 
				WHERE 
					({$vs_item_pk} = ?)
			", (int)$vn_row_id);
			if ($o_db->numErrors()) { $this->errors = $o_db->errors; return null; }
			
			while($qr_res->nextRow()) {
				$va_to_reindex_relations[(int)$qr_res->get('relation_id')] = $qr_res->getRow();
			}
			
			if (!sizeof($va_to_reindex_relations)) { return 0; }
			
			$vs_pk = $this->primaryKey();
			$vs_rel_pk = $t_item_rel->primaryKey();
			
			$va_new_relations = array();
			foreach($va_to_reindex_relations as $vn_relation_id => $va_row) {
				$t_item_rel->clear();
				unset($va_row[$vs_rel_pk]);
				$va_row[$vs_item_pk] = $pn_to_id;
				 
				$t_item_rel->set($va_row);
				$t_item_rel->insert();
				if ($t_item_rel->numErrors()) {
					$this->errors = $t_item_rel->errors; return null;	
				}
				$va_new_relations[$t_item_rel->getPrimaryKey()] = $va_row;
				
				if ($vb_copy_attributes) {
					$t_item_rel->copyAttributesFrom($vn_relation_id);
					if ($t_item_rel->numErrors()) {
						$this->errors = $t_item_rel->errors; return null;	
					}
				}
			}
		}
		
		$vn_rel_table_num = $t_item_rel->tableNum();
		
		// Reindex modified relationships
		$o_indexer = $this->getSearchIndexer();
		foreach($va_new_relations as $vn_relation_id => $va_row) {
			$o_indexer->indexRow($vn_rel_table_num, $vn_relation_id, $va_row, false, null, array($vs_item_pk => true));
		}
		
		return sizeof($va_new_relations);
	}
	# --------------------------------------------------------------------------------------------
	/**
	 *
	 */
	private function _getRelationshipInfo($pm_rel_table_name_or_num, $pb_use_cache=true) {
		$vs_table = $this->tableName();
		if ($pb_use_cache && isset(BaseModel::$s_relationship_info_cache[$vs_table][$pm_rel_table_name_or_num])) {
			return BaseModel::$s_relationship_info_cache[$vs_table][$pm_rel_table_name_or_num];
		}
		if (is_numeric($pm_rel_table_name_or_num)) {
			$vs_related_table_name = Datamodel::getTableName($pm_rel_table_name_or_num);
		} else {
			$vs_related_table_name = $pm_rel_table_name_or_num;
		}
		
		$va_rel_keys = array();
		if ($vs_table == $vs_related_table_name) {
			// self relations
			if ($vs_self_relation_table = $this->getSelfRelationTableName()) {
				$t_item_rel = Datamodel::getInstanceByTableName($vs_self_relation_table, $pb_use_cache);
			} else {
				return null;
			}
		} else {
			$va_path = array_keys(Datamodel::getPath($vs_table, $vs_related_table_name));
			
			switch(sizeof($va_path)) {
				case 3:
					$t_item_rel = Datamodel::getInstanceByTableName($va_path[1], $pb_use_cache);
					break;
				case 2:
					$t_item_rel = Datamodel::getInstanceByTableName($va_path[1], $pb_use_cache);
					if (!sizeof($va_rel_keys = Datamodel::getOneToManyRelations($vs_table, $va_path[1]))) {
						$va_rel_keys = Datamodel::getOneToManyRelations($va_path[1], $vs_table);
					}
					break;
				default:
					// bad related table
					return null;
					break;
			}
		}
		
		if ($this->inTransaction()) { $t_item_rel->setTransaction($this->getTransaction()); }
		return BaseModel::$s_relationship_info_cache[$vs_table][$vs_related_table_name] = BaseModel::$s_relationship_info_cache[$vs_table][$pm_rel_table_name_or_num] = array(
			'related_table_name' => $vs_related_table_name,
			'path' => $va_path,
			'rel_keys' => $va_rel_keys,
			't_item_rel' => $t_item_rel
		);
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Checks if a relationship exists between the currently loaded row and the specified target
	 *
	 * @param mixed $pm_rel_table_name_or_num Table name (eg. "ca_entities") or number as defined in datamodel.conf of table containing row to creation relationship to.
	 * @param int $pn_rel_id primary key value of row to creation relationship to.
	 * @param mixed $pm_type_id Relationship type type_code or type_id, as defined in the ca_relationship_types table. This is required for all relationships that use relationship types. This includes all of the most common types of relationships.
	 * @param string $ps_effective_date Optional date expression to qualify relation with. Any expression that the TimeExpressionParser can handle is supported here.
	 * @param string $ps_direction Optional direction specification for self-relationships (relationships linking two rows in the same table). Valid values are 'ltor' (left-to-right) and  'rtol' (right-to-left); the direction determines which "side" of the relationship the currently loaded row is on: 'ltor' puts the current row on the left side. For many self-relations the direction determines the nature and display text for the relationship.
	 * @param array $pa_options Options are:
	 *		relation_id = an optional relation_id to ignore when checking for existence. If you are checking for relations other than one you know exists you can set this to ensure that relationship is not considered.
	 *
	 * @return mixed Array of matched relation_ids on success, false on error.
	 */
	public function relationshipExists($pm_rel_table_name_or_num, $pn_rel_id, $pm_type_id=null, $ps_effective_date=null, $ps_direction=null, $pa_options=null) {
		$pb_use_rel_info_cache = caGetOption('useRelationshipInfoCache', $pa_options, true);
		if(!($va_rel_info = $this->_getRelationshipInfo($pm_rel_table_name_or_num, $pb_use_rel_info_cache))) {
			$this->postError(1240, _t('Related table specification "%1" is not valid', $pm_rel_table_name_or_num), 'BaseModel->addRelationship()');
			return false; 
		}
		
		$vn_relation_id = ((isset($pa_options['relation_id']) && (int)$pa_options['relation_id'])) ? (int)$pa_options['relation_id'] : null;
		$vs_relation_id_sql = null;
		
		$t_item_rel = $va_rel_info['t_item_rel'];
		
		if ($pm_type_id && !is_numeric($pm_type_id)) {
			$t_rel_type = new ca_relationship_types();
			if ($vs_linking_table = $t_rel_type->getRelationshipTypeTable($this->tableName(), $t_item_rel->tableName())) {
				$pn_type_id = $t_rel_type->getRelationshipTypeID($vs_linking_table, $pm_type_id);
			}
		} else {
			$pn_type_id = $pm_type_id;
		}
		
		if (!is_numeric($pn_rel_id)) {
			if ($t_rel_item = Datamodel::getInstanceByTableName($va_rel_info['related_table_name'], true)) {
				if ($this->inTransaction()) { $t_rel_item->setTransaction($this->getTransaction()); }
				if (($vs_idno_fld = $t_rel_item->getProperty('ID_NUMBERING_ID_FIELD')) && $t_rel_item->load(array($vs_idno_fld => $pn_rel_id))) {
					$pn_rel_id = $t_rel_item->getPrimaryKey();
				}
			}
		}
		
		$va_query_params = array();
		$o_db = $this->getDb();
		
		if (($t_item_rel = $va_rel_info['t_item_rel']) && method_exists($t_item_rel, 'getLeftTableName')) {
			$vs_rel_table_name = $t_item_rel->tableName();
		
			$vs_type_sql = $vs_timestamp_sql = '';
		
		
			$vs_left_table_name = $t_item_rel->getLeftTableName();
			$vs_left_field_name = $t_item_rel->getLeftTableFieldName();
		
			$vs_right_table_name = $t_item_rel->getRightTableName();
			$vs_right_field_name = $t_item_rel->getRightTableFieldName();
		
		
			
			if ($va_rel_info['related_table_name'] == $this->tableName()) {
				// is self relation
				if ($ps_direction == 'rtol') {
					$vn_left_id = (int)$pn_rel_id;
					$vn_right_id = (int)$this->getPrimaryKey();
				} else {
					$vn_left_id = (int)$this->getPrimaryKey();
					$vn_right_id = (int)$pn_rel_id;
				}
			} else {
				if ($vs_left_table_name == $this->tableName()) {
					$vn_left_id = (int)$this->getPrimaryKey();
					$vn_right_id = (int)$pn_rel_id;
				} else {
					$vn_left_id = (int)$pn_rel_id;
					$vn_right_id = (int)$this->getPrimaryKey();
				}
			}
		
			$va_query_params = array($vn_left_id, $vn_right_id);
		
			if ($t_item_rel->hasField('type_id')) {
				$vs_type_sql = ' AND type_id = ?';
				$va_query_params[] = (int)$pn_type_id;
			}
		
		
			if ($ps_effective_date && $t_item_rel->hasField('effective_date') && ($va_timestamps = caDateToHistoricTimestamps($ps_effective_date))) {
				$vs_timestamp_sql = " AND (sdatetime = ? AND edatetime = ?)";
				$va_query_params[] = (float)$va_timestamps['start'];
				$va_query_params[] = (float)$va_timestamps['end'];
			}
			
			if ($vn_relation_id) {
				$vs_relation_id_sql = " AND relation_id <> ?";
				$va_query_params[] = $vn_relation_id;
			}
			
			$qr_res = $o_db->query("
				SELECT relation_id
				FROM {$vs_rel_table_name}
				WHERE
					{$vs_left_field_name} = ? AND {$vs_right_field_name} = ?
					{$vs_type_sql} {$vs_timestamp_sql} {$vs_relation_id_sql}
			", $va_query_params);
		
			$va_ids = $qr_res->getAllFieldValues('relation_id');
			
			if ($va_rel_info['related_table_name'] == $this->tableName()) {
				$qr_res = $o_db->query("
					SELECT relation_id
					FROM {$vs_rel_table_name}
					WHERE
						{$vs_right_field_name} = ? AND {$vs_left_field_name} = ?
						{$vs_type_sql} {$vs_timestamp_sql} {$vs_relation_id_sql}
				", $va_query_params);
				
				$va_ids += $qr_res->getAllFieldValues('relation_id');
			}
			
			if (sizeof($va_ids)) { return $va_ids; }
		} else {
			if (sizeof($va_rel_info['path']) == 2) {		// many-one rel
				$va_rel_keys = $va_rel_info['rel_keys'];
				$vb_is_one_table = ($this->tableName() == $va_rel_keys['one_table']) ? true : false;
			
				$vs_where_sql = "(ot.".$va_rel_keys['one_table_field']." = ?) AND (mt.".$va_rel_keys['many_table_field']." = ?)";
				
				if ($vb_is_one_table) {
					$va_query_params[] = (int)$this->getPrimaryKey();
					$va_query_params[] = (int)$pn_rel_id;
				} else {
					$va_query_params[] = (int)$pn_rel_id;
					$va_query_params[] = (int)$this->getPrimaryKey();	
				}
			
				$vs_relation_id_fld = ($vb_is_one_table ? "mt.".$va_rel_keys['many_table_field'] : "ot.".$va_rel_keys['one_table_field']);
				$qr_res = $o_db->query($x="
					SELECT {$vs_relation_id_fld}
					FROM {$va_rel_keys['one_table']} ot
					INNER JOIN {$va_rel_keys['many_table']} AS mt ON mt.{$va_rel_keys['many_table_field']} = ot.{$va_rel_keys['one_table_field']}
					WHERE
						{$vs_where_sql}
				", $va_query_params);
				if (sizeof($va_ids = $qr_res->getAllFieldValues($vs_relation_id_fld))) {
					return $va_ids;
				}
			}
		}
		
		return false;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Checks if any relationships exists between the currently loaded row and any other record.
	 * Returns a list of tables for which relationships exist.
	 *
	 * @param array $pa_options Options are:
	 *		None yet
	 *
	 * @return mixed Array of table names for which this row has at least one relationship, with keys set to table names and values set to the number of relationships per table.
	 */
	public function hasRelationships($pa_options=null) {
		$va_one_to_many_relations = Datamodel::getOneToManyRelations($this->tableName());

		if (is_array($va_one_to_many_relations)) {
			$o_db = $this->getDb();
			$vn_id = $this->getPrimaryKey();
			$o_trans = $this->getTransaction();
			
			$va_tables = array();
			foreach($va_one_to_many_relations as $vs_many_table => $va_info) {
				foreach($va_info as $va_relationship) {
					# do any records exist?
					$vs_rel_pk = Datamodel::primaryKey($vs_many_table);
					
					$qr_record_check = $o_db->query($x="
						SELECT {$vs_rel_pk}
						FROM {$vs_many_table}
						WHERE
							({$va_relationship['many_table_field']} = ?)"
					, array((int)$vn_id));
					
					if (($vn_count = $qr_record_check->numRows()) > 0) {
						$va_tables[$vs_many_table] = $vn_count;	
					}
				}
			}
			return $va_tables;
		}
		
		return null;
	}
	
	# --------------------------------------------------------------------------------------------
	/**
	 * Returns name of self relation table (table that links two rows in this table) or NULL if no table exists
	 *
	 * @return string Name of table or null if no table is defined.
	 */
	public function getSelfRelationTableName() {
		if (isset($this->SELF_RELATION_TABLE_NAME)) {
			return $this->SELF_RELATION_TABLE_NAME;
		}
		return null;
	}	
		# ------------------------------------------------------
	/**
	 * @param RequestHTTP $po_request
	 * @param string $ps_form_name
	 * @param string $ps_related_table
	 * @param null|string $ps_placement_code
	 * @param null|array $pa_bundle_settings
	 * @param null|arrau $pa_options
	 * @return array|mixed
	 */
	public function getRelatedBundleFormValues($po_request, $ps_form_name, $ps_related_table, $ps_placement_code=null, $pa_bundle_settings=null, $pa_options=null) {
		if(!is_array($pa_bundle_settings)) { $pa_bundle_settings = array(); }
		if(!is_array($pa_options)) { $pa_options = array(); }

		/** @var BundlableLabelableBaseModelWithAttributes $t_item */
		$t_item = Datamodel::getInstanceByTableName($ps_related_table);
		$vb_is_many_many = false;
		
		$va_path = array_keys(Datamodel::getPath($this->tableName(), $ps_related_table));
		if ($this->tableName() == $ps_related_table) {
			// self relationship
			$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
			$vb_is_many_many = true;
		} else {
			switch(sizeof($va_path)) {
				case 3:
					// many-many relationship
					$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
					$vb_is_many_many = true;
					break;
				case 2:
					// many-one relationship
					$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
					break;
				default:
					$t_item_rel = null;
					break;
			}
		}
		
		$va_get_related_opts = array_merge($pa_options, $pa_bundle_settings);
		if (isset($pa_bundle_settings['restrictToTermsRelatedToCollection']) && $pa_bundle_settings['restrictToTermsRelatedToCollection']) {
			$va_get_related_opts['restrict_to_relationship_types'] = $pa_bundle_settings['restrictToTermsOnCollectionUseRelationshipType'];
		}
			
		if ($pa_bundle_settings['sort']) {
			$va_get_related_opts['sort'] = $pa_bundle_settings['sort'];
			$va_get_related_opts['sortDirection'] = $pa_bundle_settings['sortDirection'];
		}

		$t_rel = Datamodel::getInstanceByTableName($ps_related_table, true);
		$va_opts = [
			'table' => $vb_is_many_many ? $t_rel->tableName() : null,
			'primaryKey' => $vb_is_many_many ? $t_rel->primaryKey() : null,
			'template' => caGetBundleDisplayTemplate($this, $ps_related_table, $pa_bundle_settings),
			'primaryIDs' => array($this->tableName() => array($this->getPrimaryKey())),
			'request' => $po_request,
			'stripTags' => true
		];

		if($ps_related_table == 'ca_sets') {
			// sets special case
			
			$t_set = new ca_sets();
			$va_items = caExtractValuesByUserLocale($t_set->getSetsForItem($this->tableNum(), $this->getPrimaryKey(), $va_get_related_opts));

			// sort
			if($ps_sort = caGetOption('sort', $va_get_related_opts, null)) {
				$va_items = caSortArrayByKeyInValue($va_items, array($ps_sort), caGetOption('sortDirectio ', $va_get_related_opts, 'ASC'));
			}

			$va_vals = [];
			$vs_template = caGetBundleDisplayTemplate($this, 'ca_sets', $pa_bundle_settings);
			if(is_array($va_items) && sizeof($va_items)) {
				foreach($va_items as $vn_id => $va_item) {
					$va_item['_display'] = caProcessTemplateForIDs($vs_template, 'ca_sets', array($vn_id));
					$va_vals[$vn_id] = $va_item;
				}
			}

			return $va_vals;
		} elseif(($ps_related_table == 'ca_objects') && ($this->tableName() == 'ca_storage_locations') && (strlen($vs_mode = $pa_bundle_settings['locationTrackingMode']) > 0)) {
			// Limit list to objects _currently_ in this location
			if(!($qr_results = $this->getLocationContents($vs_mode))) { return []; }
			
			if (sizeof($va_ids = $qr_results->getAllFieldValues('ca_objects.object_id')) == 0) { return []; }
			$qr_rel_items = caMakeSearchResult('ca_objects', $va_ids);
			
			return caProcessRelationshipLookupLabel($qr_rel_items, $t_item_rel, $va_opts);
		} elseif (sizeof($va_items = $this->getRelatedItems($ps_related_table, $va_get_related_opts))) {
			// Show fill list
			
			$va_opts['relatedItems'] = $va_items;
			if ($vb_is_many_many) {
				$va_ids = caExtractArrayValuesFromArrayOfArrays($va_items, 'relation_id');
				$qr_rel_items = $t_item->makeSearchResult($t_item_rel->tableNum(), $va_ids);
			} else {
				$va_ids = caExtractArrayValuesFromArrayOfArrays($va_items, $t_rel->primaryKey());
				$qr_rel_items = $t_item->makeSearchResult($t_rel->tableNum(), $va_ids);
			}

			return caProcessRelationshipLookupLabel($qr_rel_items, $t_item_rel, $va_opts);
		}

		return array();
	}
 	# ------------------------------------------------------
 	/**
 	 *
 	 */
	public function getRelatedHTMLFormBundle($po_request, $ps_form_name, $ps_related_table, $ps_placement_code=null, $pa_bundle_settings=null, $pa_options=null) {
		global $g_ui_locale;
		AssetLoadManager::register('sortableUI');
		
		if(!is_array($pa_bundle_settings)) { $pa_bundle_settings = array(); }
		if(!is_array($pa_options)) { $pa_options = array(); }
		
		$vs_view_path = (isset($pa_options['viewPath']) && $pa_options['viewPath']) ? $pa_options['viewPath'] : $po_request->getViewsDirectoryPath();
		$o_view = new View($po_request, "{$vs_view_path}/bundles/");
		
		$t_item = Datamodel::getInstanceByTableName($ps_related_table);
		$vb_is_many_many = false;
		
		$va_path = array_keys(Datamodel::getPath($this->tableName(), $ps_related_table));
		if ($this->tableName() == $ps_related_table) {
			// self relationship
			$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
			$vb_is_many_many = true;
		} else {
			switch(sizeof($va_path)) {
				case 3:
					// many-many relationship
					$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
					$vb_is_many_many = true;
					break;
				case 2:
					// many-one relationship
					$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
					break;
				default:
					if($ps_related_table == 'ca_sets') {
						$t_item_rel = new ca_sets();
					} else {
						$t_item_rel = null;
					}
					break;
			}
		}
		
		$o_view->setVar('id_prefix', $ps_form_name);
		$o_view->setVar('t_instance', $this);
		$o_view->setVar('t_item', $t_item);
		$o_view->setVar('t_item_rel', $t_item_rel);
		$o_view->setVar('bundle_name', $ps_related_table);
		
		$o_view->setVar('ui', caGetOption('ui', $pa_options, null));
		$o_view->setVar('screen', caGetOption('screen', $pa_options, null));
		
		$vb_read_only = ($po_request->user->getBundleAccessLevel($this->tableName(), $ps_related_table) == __CA_BUNDLE_ACCESS_READONLY__) ? true : false;
		if (!$pa_bundle_settings['readonly']) { $pa_bundle_settings['readonly'] = (!isset($pa_bundle_settings['readonly']) || !$pa_bundle_settings['readonly']) ? $vb_read_only : true;	}
		
		// pass bundle settings
		
		if(!is_array($pa_bundle_settings['prepopulateQuickaddFields'])) { $pa_bundle_settings['prepopulateQuickaddFields'] = []; }
		$o_view->setVar('settings', $pa_bundle_settings);
		$o_view->setVar('graphicsPath', $pa_options['graphicsPath']);
		
		// pass placement code
		$o_view->setVar('placement_code', $ps_placement_code);
		
		// quickadd available?
		$vb_quickadd_enabled = (bool)$po_request->user->canDoAction("can_quickadd_{$ps_related_table}");
		if ($pa_bundle_settings['disableQuickadd']) { $vb_quickadd_enabled = false; }
		$o_view->setVar('quickadd_enabled', $vb_quickadd_enabled);
		
		$o_view->setVar('add_label', caExtractSettingValueByLocale($pa_bundle_settings, 'add_label', $g_ui_locale));
		
		$t_label = null;
		if ($t_item->getLabelTableName()) {
			$t_label = Datamodel::getInstanceByTableName($t_item->getLabelTableName(), true);
		}
		if (method_exists($t_item_rel, 'getRelationshipTypes')) {
			$o_view->setVar('relationship_types', $t_item_rel->getRelationshipTypes(null, null,  array_merge($pa_options, $pa_bundle_settings)));
			$o_view->setVar('relationship_types_by_sub_type', $t_item_rel->getRelationshipTypesBySubtype($this->tableName(), $this->get('type_id'),  array_merge($pa_options, $pa_bundle_settings)));
		}
		$o_view->setVar('t_subject', $this);

		$va_initial_values = $this->getRelatedBundleFormValues($po_request, $ps_form_name, $ps_related_table, $ps_placement_code, $pa_bundle_settings, $pa_options);

		$va_force_new_values = array();
		if (isset($pa_options['force']) && is_array($pa_options['force'])) {
			foreach($pa_options['force'] as $vn_id) {
				if ($t_item->load($vn_id)) {
					$va_item = $t_item->getFieldValuesArray();
					if ($t_label) {
						$va_item[$t_label->getDisplayField()] =  $t_item->getLabelForDisplay();
					}
					$va_force_new_values[$vn_id] = array_merge(
						$va_item, 
						array(
							'id' => $vn_id, 
							'idno' => ($vn_idno = $t_item->get('idno')) ? $vn_idno : null, 
							'idno_stub' => ($vn_idno_stub = $t_item->get('idno_stub')) ? $vn_idno_stub : null, 
							'item_type_id' => $t_item->getTypeID(),
							'relationship_type_id' => null
						)
					);
				}
			}
		}
		
		$o_view->setVar('defaultRepresentationUploadType', $po_request->user->getVar('defaultRepresentationUploadType'));
		
		$o_view->setVar('initialValues', $va_initial_values);
		$o_view->setVar('forceNewValues', $va_force_new_values);
		$o_view->setVar('batch', (bool)(isset($pa_options['batch']) && $pa_options['batch']));
		
		return $o_view->render($ps_related_table.'.php');
	}
	# ------------------------------------------------------
	/**
	 * @param RequestHTTP $po_request
	 * @param string $ps_bundle_name
	 * @param string $ps_form_name
	 * @param null|string $ps_placement_code
	 * @param null|array $pa_bundle_settings
	 * @param null|array $pa_options
	 * @return mixed|null|string
	 */
	public function getRelatedListHTMLFormBundle($po_request, $ps_bundle_name, $ps_form_name, $ps_placement_code=null, $pa_bundle_settings=null, $pa_options=null) {
		global $g_ui_locale;

		if(!is_array($pa_bundle_settings)) { $pa_bundle_settings = array(); }
		if(!is_array($pa_options)) { $pa_options = array(); }

		$vs_table_name = preg_replace("/_related_list|_table$/", '', $ps_bundle_name);
		$vs_view_path = (isset($pa_options['viewPath']) && $pa_options['viewPath']) ? $pa_options['viewPath'] : $po_request->getViewsDirectoryPath();
		$o_view = new View($po_request, "{$vs_view_path}/bundles/");

		$va_path = array_keys(Datamodel::getPath($this->tableName(), $vs_table_name));
		$t_item = new $vs_table_name;
		/** @var BaseRelationshipModel $t_item_rel */
		$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);

		$o_view->setVar('id_prefix', $ps_form_name);
		$o_view->setVar('bundle_name', $ps_bundle_name);
		$o_view->setVar('t_instance', $this);
		$o_view->setVar('t_subject', $this);
		$o_view->setVar('t_item', $t_item);
		$o_view->setVar('t_item_rel', $t_item_rel);

		$vb_read_only = ($po_request->user->getBundleAccessLevel($this->tableName(), $ps_bundle_name) == __CA_BUNDLE_ACCESS_READONLY__) ? true : false;
		if (!$pa_bundle_settings['readonly']) { $pa_bundle_settings['readonly'] = (!isset($pa_bundle_settings['readonly']) || !$pa_bundle_settings['readonly']) ? $vb_read_only : true;	}

		if(!is_array($pa_bundle_settings['prepopulateQuickaddFields'])) { $pa_bundle_settings['prepopulateQuickaddFields'] = []; }
		$o_view->setVar('settings', $pa_bundle_settings);
		$o_view->setVar('placement_code', $ps_placement_code);
		$o_view->setVar('add_label', caExtractSettingValueByLocale($pa_bundle_settings, 'add_label', $g_ui_locale));

		$o_view->setVar('relationship_types', method_exists($t_item_rel, 'getRelationshipTypes') ? $t_item_rel->getRelationshipTypes(null, null,  array_merge($pa_options, $pa_bundle_settings)) : []);
		$o_view->setVar('relationship_types_by_sub_type',  method_exists($t_item_rel, 'getRelationshipTypesBySubtype') ? $t_item_rel->getRelationshipTypesBySubtype($this->tableName(), $this->get('type_id'),  array_merge($pa_options, $pa_bundle_settings)) : []);

		$va_initial_values = $this->getRelatedBundleFormValues($po_request, $ps_form_name, $vs_table_name, $ps_placement_code, $pa_bundle_settings, $pa_options);

		$o_view->setVar('initialValues', $va_initial_values);
		$o_view->setVar('result', caMakeSearchResult($vs_table_name, array_keys($va_initial_values)));
		$o_view->setVar('batch', (bool)(isset($pa_options['batch']) && $pa_options['batch']));

		return $o_view->render('related_list.php');
	}
	 	# ------------------------------------------------------
 	/**
 	 *
 	 */
 	private function _processRelated($po_request, $ps_bundle_name, $ps_form_prefix, $ps_placement_code, $pa_options=null) {
 		$pa_settings = caGetOption('settings', $pa_options, array());
 		$vb_batch = caGetOption('batch', $pa_options, false);
		
		$vn_min_relationships = caGetOption('minRelationshipsPerRow', $pa_settings, 0);
		$vn_max_relationships = caGetOption('maxRelationshipsPerRow', $pa_settings, 65535);
		if ($vn_max_relationships == 0) { $vn_max_relationships = 65535; }
		
 		$va_rel_ids_sorted = $va_rel_sort_order = explode(';',$po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}BundleList", pString));
		sort($va_rel_ids_sorted, SORT_NUMERIC);
						
 		$va_rel_items = $this->getRelatedItems($ps_bundle_name, $pa_settings);
 		
 		$va_rels_to_add = $va_rels_to_delete = array();
 if(!$vb_batch) {	
		foreach($va_rel_items as $va_rel_item) {
			$vs_key = $va_rel_item['_key'];
			
			$vn_rank = null;
			if (($vn_rank_index = array_search($va_rel_item['relation_id'], $va_rel_sort_order)) !== false) {
				$vn_rank = $va_rel_ids_sorted[$vn_rank_index];
			}
			
			$this->clearErrors();
			$vn_id = $po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}_id".$va_rel_item[$vs_key], pString);
			if ($vn_id) {
				$vn_type_id = $po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}_type_id".$va_rel_item[$vs_key], pString);
				$vs_direction = null;
				if (sizeof($va_tmp = explode('_', $vn_type_id)) == 2) {
					$vn_type_id = (int)$va_tmp[1];
					$vs_direction = $va_tmp[0];
				}
				
				$vs_effective_daterange = $po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}_effective_date".$va_rel_item[$vs_key], pString);
				$this->editRelationship($ps_bundle_name, $va_rel_item[$vs_key], $vn_id, $vn_type_id, null, null, $vs_direction, $vn_rank);	
					
				if ($this->numErrors()) {
					$po_request->addActionErrors($this->errors(), $ps_bundle_name);
				}
			} else {
				// is it a delete key?
				$this->clearErrors();
				if (($po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}_".$va_rel_item[$vs_key].'_delete', pInteger)) > 0) {
					$va_rels_to_delete[] = array('bundle' => $ps_bundle_name, 'relation_id' => $va_rel_item[$vs_key]);
				}
			}
		}
}
 		
 		// process batch remove
 		if ($vb_batch) {
			$vs_batch_mode = $_REQUEST["{$ps_placement_code}{$ps_form_prefix}_batch_mode"];
 			if ($vs_batch_mode == '_disabled_') { return true; }
			if ($vs_batch_mode == '_delete_') {				// remove all relationships and return
				$this->removeRelationships($ps_bundle_name, caGetOption('restrict_to_relationship_types', $pa_settings, null), ['restrictToTypes' => caGetOption('restrict_to_types', $pa_settings, null)]);
				return true;
			}
			if ($vs_batch_mode == '_replace_') {			// remove all existing relationships and then add new ones
				$this->removeRelationships($ps_bundle_name, caGetOption('restrict_to_relationship_types', $pa_settings, null), ['restrictToTypes' => caGetOption('restrict_to_types', $pa_settings, null)]);
			}
		}
		
 		// check for new relations to add
 		foreach($_REQUEST as $vs_key => $vs_value ) {
			if (preg_match("/^{$ps_placement_code}{$ps_form_prefix}_idnew_([\d]+)/", $vs_key, $va_matches)) { 
				$vn_c = intval($va_matches[1]);
				if ($vn_new_id = $po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}_idnew_{$vn_c}", pString)) {
					$vn_new_type_id = $po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}_type_idnew_{$vn_c}", pString);
				
					$vs_direction = null;
					if (sizeof($va_tmp = explode('_', $vn_new_type_id)) == 2) {
						$vn_new_type_id = (int)$va_tmp[1];
						$vs_direction = $va_tmp[0];
					}
				
					$va_rels_to_add[] = array(
						'bundle' => $ps_bundle_name, 'row_id' => $vn_new_id, 'type_id' => $vn_new_type_id, 'direction' => $vs_direction
					);
				}
			}
			
			// check for checklist mode ca_list_items
			if ($ps_bundle_name == 'ca_list_items') {
				if (preg_match("/^{$ps_placement_code}{$ps_form_prefix}_item_id_new_([\d]+)/", $vs_key, $va_matches)) { 
					if ($vn_rel_type_id = $po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}_type_idchecklist", pInteger)) {
						if ($vn_item_id = $po_request->getParameter($vs_key, pInteger)) {
							$va_rels_to_add[] = array(
								'bundle' => $ps_bundle_name, 'row_id' => $vn_item_id, 'type_id' => $vn_rel_type_id, 'direction' => null
							);
						}
					}
				}
				
				if (preg_match("/^{$ps_placement_code}{$ps_form_prefix}_item_id_([\d]+)_delete/", $vs_key, $va_matches)) { 
					if ($po_request->getParameter($vs_key, pInteger)) {
						$va_rels_to_delete[] = array('bundle' => $ps_bundle_name, 'relation_id' => $va_matches[1]);
					}
				}
			}
		}
		
		// Check min/max
		$vn_total_rel_count = (sizeof($va_rel_items) + sizeof($va_rels_to_add) - sizeof($va_rels_to_delete));
		if ($vn_min_relationships && ($vn_total_rel_count < $vn_min_relationships)) {
			$po_request->addActionErrors(array(new ApplicationError(2590, ($vn_min_relationships == 1) ? _t('There must be at least %1 relationship for %2', $vn_min_relationships, Datamodel::getTableProperty($ps_bundle_name, 'NAME_PLURAL')) : _t('There must be at least %1 relationships for %2', $vn_min_relationships, Datamodel::getTableProperty($ps_bundle_name, 'NAME_PLURAL')), 'BundleableLabelableBaseModelWithAttributes::_processRelated()', null, null, false, false)), $ps_bundle_name);
			return false;
		}
		if ($vn_max_relationships && ($vn_total_rel_count > $vn_max_relationships)) {
			$po_request->addActionErrors(array(new ApplicationError(2590, ($vn_max_relationships == 1) ? _t('There must be no more than %1 relationship for %2', $vn_max_relationships, Datamodel::getTableProperty($ps_bundle_name, 'NAME_PLURAL')) : _t('There must be no more than %1 relationships for %2', $vn_max_relationships, Datamodel::getTableProperty($ps_bundle_name, 'NAME_PLURAL')), 'BundleableLabelableBaseModelWithAttributes::_processRelated()', null, null, false, false)), $ps_bundle_name);
			return false;
		}
		
		// Process relationships
		foreach($va_rels_to_delete as $va_rel_to_delete) {
			$this->removeRelationship($va_rel_to_delete['bundle'], $va_rel_to_delete['relation_id']);
			if ($this->numErrors()) {
				$po_request->addActionErrors($this->errors(), $ps_bundle_name);
			}
		}
		foreach($va_rels_to_add as $va_rel_to_add) {
			$this->addRelationship($va_rel_to_add['bundle'], $va_rel_to_add['row_id'], $va_rel_to_add['type_id'], null, null, $va_rel_to_add['direction']);
			if ($this->numErrors()) {
				$po_request->addActionErrors($this->errors(), $ps_bundle_name);
			}
		}
		
		return true;
 	}

	/**
	 * @param RequestHTTP $po_request
	 * @param string $ps_form_prefix
	 * @param string $ps_placement_code
	 */
	public function _processRelatedSets($po_request, $ps_form_prefix, $ps_placement_code) {
		require_once(__CA_MODELS_DIR__ . '/ca_sets.php');

		foreach($_REQUEST as $vs_key => $vs_value ) {
			// check for new relationships to add
			if (preg_match("/^{$ps_placement_code}{$ps_form_prefix}_idnew_([\d]+)/", $vs_key, $va_matches)) {
				$vn_c = intval($va_matches[1]);
				if ($vn_new_id = $po_request->getParameter("{$ps_placement_code}{$ps_form_prefix}_idnew_{$vn_c}", pString)) {
					$t_set = new ca_sets($vn_new_id);
					$t_set->addItem($this->getPrimaryKey(), null, $po_request->getUserID());
				}
			}

			// check for delete keys
			if (preg_match("/^{$ps_placement_code}{$ps_form_prefix}_([\d]+)_delete/", $vs_key, $va_matches)) {
				$vn_c = intval($va_matches[1]);
				$t_set = new ca_sets($vn_c);
				$t_set->removeItem($this->getPrimaryKey());
			}


		}
	}
 	# ------------------------------------------------------
 	/**
 	 *
 	 */
 	public function getRelatedItemsAsSearchResult($pm_rel_table_name_or_num, $pa_options=null) {
 		if (is_array($va_related_ids = $this->getRelatedItems($pm_rel_table_name_or_num, array_merge($pa_options, array('idsOnly' => true))))) {
 			
 			$va_ids = array_map(function($pn_v) {
 				return ($pn_v > 0) ? true : false;
 			}, $va_related_ids);
 			
 			return $this->makeSearchResult($pm_rel_table_name_or_num, $va_ids);
 		}
 		return null;
 	}
 	# ------------------------------------------------------
 	/**
 	 * Returns list of items in the specified table related to the currently loaded row or rows specified in options.
 	 * 
 	 * @param mixed $pm_rel_table_name_or_num The table name or table number of the item type you want to get a list of (eg. if you are calling this on an ca_objects instance passing 'ca_entities' here will get you a list of entities related to the object)
 	 * @param array $pa_options Array of options. Supported options are:
 	 *
 	 *		[Options controlling rows for which data is returned]
 	 *			row_ids = Array of primary key values to use when fetching related items. If omitted or set to a null value the 'row_id' option will be used. [Default is null]
 	 *			row_id = Primary key value to use when fetching related items. If omitted or set to a false value (null, false, 0) then the primary key value of the currently loaded row is used. [Default is currently loaded row]
 	 *			start = Zero-based index to begin return set at. [Default is 0]
 	 *			limit = Maximum number of related items to return. [Default is 1000]
 	 *			showDeleted = Return related items that have been deleted. [Default is false]
 	 *			primaryIDs = array of primary keys in related table to exclude from returned list of items. Array is keyed on table name for compatibility with the parameter as used in the caProcessTemplateForIDs() helper [Default is null - nothing is excluded].
 	 *			restrictToBundleValues = Restrict returned items to those with specified bundle values. Specify an associative array with keys set to bundle names and key values set to arrays of values to filter on (eg. [bundle_name1 => [value1, value2, ...]]). [Default is null]
 	 *			where = Restrict returned items to specified field values. The fields must be intrinsic and in the related table. This option can be useful when you want to efficiently fetch specific rows from a related table. Note that multiple fields/values are logically AND'ed together – all must match for a row to be returned - and that only equivalence is supported. [Default is null]			
 	 *			criteria = Restrict returned items using SQL criteria appended directly onto the query. Criteria is used as-is and must be compatible with the generated SQL query. [Default is null]
 	 *			showCurrentOnly = Returns the relationship with the latest effective date for the row_id that is not greater than the current date. This option is only supported for standard many-many self and non-self relations and is ignored for all other kinds of relationships. [Default is false]
 	 *			showCurrentUsingDate = Bundle (intrinsic or attribute) to use to select the "current" relationship. [Default is effective_date]
 	 *			currentOnly = Synonym for showCurrentOnly
 	 *		
 	 *		[Options controlling scope of data in return value]
 	 *			restrictToTypes = Restrict returned items to those of the specified types. An array or comma/semicolon delimited string of list item idnos and/or item_ids may be specified. [Default is null]
 	 *			restrictToRelationshipTypes =  Restrict returned items to those related using the specified relationship types. An array or comma/semicolon delimited string of relationship type idnos and/or type_ids may be specified. [Default is null]
 	 *			excludeTypes = Restrict returned items to those *not* of the specified types. An array or comma/semicolon delimited string of list item idnos and/or item_ids may be specified. [Default is null]
 	 *			excludeRelationshipTypes = Restrict returned items to those *not* related using the specified relationship types. An or comma/semicolon delimited string array of relationship type idnos and/or type_ids may be specified. [Default is null]
 	 *			restrictToType = Synonym for restrictToTypes. [Default is null]
 	 *			restrictToRelationshipType = Synonym for restrictToRelationshipTypes. [Default is null]
 	 *			excludeType = Synonym for excludeTypes. [Default is null]
 	 *			excludeRelationshipType = Synonym for excludeRelationshipTypes. [Default is null]
 	 *			restrictToLists = Restrict returned items to those that are in one or more specified lists. This option is only relevant when fetching related ca_list_items. An array or comma/semicolon delimited string of list list_codes or list_ids may be specified. [Default is null]
 	 * 			fields = array of fields (in table.fieldname format) to include in returned data. [Default is null]
 	 *			returnNonPreferredLabels = Return non-preferred labels in returned data. [Default is false]
 	 *			returnLabelsAsArray = Return all labels associated with row in an array, rather than as a text value in the current locale. [Default is false]
 	 *			dontReturnLabels = Don't include labels in returned data. [Default is false]
 	 *			idsOnly = Return one-dimensional array of related primary key values only. [Default is false]
 	 *
 	 *		[Options controlling format of data in return value]
 	 *			useLocaleCodes = Return locale values as codes (Ex. en_US) rather than numeric database-specific locale_ids. [Default is false]
 	 *			sort = Array list of bundles to sort returned values on. The sortable bundle specifiers are fields with or without tablename. Only those fields returned for the related table (intrinsics, attributes and label fields) are sortable. [Default is null]
	 *			sortDirection = Direction of sort. Use "asc" (ascending) or "desc" (descending). [Default is asc]
 	 *			groupFields = Groups together fields in an arrangement that is easier for import to another system. Used by the ItemInfo web service when in "import" mode. [Default is false]
 	 *
 	 *		[Front-end access control]	
 	 *			checkAccess = Array of access values to filter returned values on. Available for any related table with an "access" field (ca_objects, ca_entities, etc.). If omitted no filtering is performed. [Default is null]
 	 *			user_id = Perform item level access control relative to specified user_id rather than currently logged in user. [Default is user_id for currently logged in user]
 	 *
 	 *		[Options controlling format of data in return value]
 	 *			returnAs = format of return value; possible values are:
 	 *				data					= return array of data about each related item [default]
	 *				searchResult			= a search result instance (aka. a subclass of BaseSearchResult) 
	 *				ids						= an array of ids (aka. primary keys); same as setting the 'idsOnly' option
	 *				modelInstances			= an array of instances, one for each match. Each instance is the  class of the related item, a subclass of BaseModel 
	 *				firstId					= the id (primary key) of the first match. This is the same as the first item in the array returned by 'ids'
	 *				firstModelInstance		= the instance of the first match. This is the same as the first instance in the array returned by 'modelInstances'
	 *				count					= the number of related items
	 *
	 *					Default is "data" - returns a list of arrays with data about each related item
 	 *
 	 * @param int $pn_count Variable to return number of related items. The count reflects the absolute number of related items, independent of how the start and limit options are set, and may differ from the number of items actually returned.
 	 *
 	 * @return array List of related items
 	 */
	public function getRelatedItems($pm_rel_table_name_or_num, $pa_options=null, &$pn_count=null) {
		global $AUTH_CURRENT_USER_ID;
						        
		$vn_user_id = (isset($pa_options['user_id']) && $pa_options['user_id']) ? $pa_options['user_id'] : (int)$AUTH_CURRENT_USER_ID;
		$vb_show_if_no_acl = (bool)($this->getAppConfig()->get('default_item_access_level') > __CA_ACL_NO_ACCESS__);

		if (caGetOption('idsOnly', $pa_options, false)) { $pa_options['returnAs'] = 'ids'; }		// 'idsOnly' is synonym for returnAs => 'ids'

		$ps_return_as = caGetOption('returnAs', $pa_options, 'data', array('forceLowercase' => true, 'validValues' => array('data', 'searchResult', 'ids', 'modelInstances', 'firstId', 'firstModelInstance', 'count')));

		// convert options
		if (($pa_options['restrictToTypes'] = caGetOption(array('restrictToTypes', 'restrict_to_types', 'restrictToType', 'restrict_to_type'), $pa_options, null)) && !is_array($pa_options['restrictToTypes'])) {
			$pa_options['restrictToTypes'] = preg_split("![;,]{1}!", $pa_options['restrictToTypes']);
		}
		if (($pa_options['restrictToRelationshipTypes'] = caGetOption(array('restrictToRelationshipTypes', 'restrict_to_relationship_types', 'restrictToRelationshipType', 'restrict_to_relationship_type'), $pa_options, null)) && !is_array($pa_options['restrictToRelationshipTypes'])) {
			$pa_options['restrictToRelationshipTypes'] = preg_split("![;,]{1}!", $pa_options['restrictToRelationshipTypes']);
		}
		if (($pa_options['excludeTypes'] = caGetOption(array('excludeTypes', 'exclude_types', 'excludeType', 'exclude_type'), $pa_options, null)) && !is_array($pa_options['excludeTypes'])) {
			$pa_options['excludeTypes'] = preg_split("![;,]{1}!", $pa_options['excludeTypes']);
		}
		if (($pa_options['excludeRelationshipTypes'] = caGetOption(array('excludeRelationshipTypes', 'exclude_relationship_types', 'excludeRelationshipType', 'exclude_relationship_type'), $pa_options, null)) && !is_array($pa_options['excludeRelationshipTypes'])) {
			$pa_options['excludeRelationshipTypes'] = preg_split("![;,]{1}!", $pa_options['excludeRelationshipTypes']);
		}
		
		if (!isset($pa_options['dontIncludeSubtypesInTypeRestriction']) && (isset($pa_options['dont_include_subtypes_in_type_restriction']) && $pa_options['dont_include_subtypes_in_type_restriction'])) { $pa_options['dontIncludeSubtypesInTypeRestriction'] = $pa_options['dont_include_subtypes_in_type_restriction']; }
		if (!isset($pa_options['returnNonPreferredLabels']) && (isset($pa_options['restrict_to_type']) && $pa_options['restrict_to_type'])) { $pa_options['returnNonPreferredLabels'] = $pa_options['restrict_to_type']; }
		if (!isset($pa_options['returnLabelsAsArray']) && (isset($pa_options['return_labels_as_array']) && $pa_options['return_labels_as_array'])) { $pa_options['returnLabelsAsArray'] = $pa_options['return_labels_as_array']; }
		if (!isset($pa_options['restrictToLists']) && (isset($pa_options['restrict_to_lists']) && $pa_options['restrict_to_lists'])) { $pa_options['restrictToLists'] = $pa_options['restrict_to_lists']; }
		
		if (($pa_options['restrictToLists'] = caGetOption(array('restrictToLists', 'restrict_to_lists'), $pa_options, null)) && !is_array($pa_options['restrictToLists'])) {
			$pa_options['restrictToLists'] = preg_split("![;,]{1}!", $pa_options['restrictToLists']);
		}
		
		$pb_group_fields = isset($pa_options['groupFields']) ? $pa_options['groupFields'] : false;
		$pa_primary_ids = (isset($pa_options['primaryIDs']) && is_array($pa_options['primaryIDs'])) ? $pa_options['primaryIDs'] : null;
		$pb_show_current_only = caGetOption('showCurrentOnly', $pa_options, caGetOption('currentOnly', $pa_options, false));
		$ps_current_date_bundle = caGetOption('showCurrentUsingDate', $pa_options, 'effective_date');
		
		if (!isset($pa_options['useLocaleCodes']) && (isset($pa_options['returnLocaleCodes']) && $pa_options['returnLocaleCodes'])) { $pa_options['useLocaleCodes'] = $pa_options['returnLocaleCodes']; }
		$pb_use_locale_codes = isset($pa_options['useLocaleCodes']) ? $pa_options['useLocaleCodes'] : false;
		
		$pa_get_where = (isset($pa_options['where']) && is_array($pa_options['where']) && sizeof($pa_options['where'])) ? $pa_options['where'] : null;

		$pa_row_ids = (isset($pa_options['row_ids']) && is_array($pa_options['row_ids'])) ? $pa_options['row_ids'] : null;
		$pn_row_id = (isset($pa_options['row_id']) && $pa_options['row_id']) ? $pa_options['row_id'] : $this->getPrimaryKey();

		$o_db = $this->getDb();
		$t_locale = $this->getLocaleInstance();
		$o_tep = $this->getTimeExpressionParser();
		
		$vb_uses_effective_dates = false;
		$vn_current_date = TimeExpressionParser::now();

		if(isset($pa_options['sort']) && !is_array($pa_options['sort'])) { $pa_options['sort'] = array($pa_options['sort']); }
		$pa_sort_fields = (isset($pa_options['sort']) && is_array($pa_options['sort'])) ? array_filter($pa_options['sort'], "strlen") : null;
		$ps_sort_direction = (isset($pa_options['sortDirection']) && $pa_options['sortDirection']) ? $pa_options['sortDirection'] : null;

		if (!$pa_row_ids && ($pn_row_id > 0)) {
			$pa_row_ids = array($pn_row_id);
		}

		if (!$pa_row_ids || !is_array($pa_row_ids) || !sizeof($pa_row_ids)) { return array(); }

		$pb_return_labels_as_array = (isset($pa_options['returnLabelsAsArray']) && $pa_options['returnLabelsAsArray']) ? true : false;
		$pn_limit = (isset($pa_options['limit']) && ((int)$pa_options['limit'] > 0)) ? (int)$pa_options['limit'] : 1000;
		$pn_start = (isset($pa_options['start']) && ((int)$pa_options['start'] > 0)) ? (int)$pa_options['start'] : 0;

		if (is_numeric($pm_rel_table_name_or_num)) {
			if(!($vs_related_table_name = Datamodel::getTableName($pm_rel_table_name_or_num))) { return null; }
		} else {
			if (sizeof($va_tmp = explode(".", $pm_rel_table_name_or_num)) > 1) {
				$pm_rel_table_name_or_num = array_shift($va_tmp);
			}
			if (!($o_instance = Datamodel::getInstanceByTableName($pm_rel_table_name_or_num, true))) { return null; }
			$vs_related_table_name = $pm_rel_table_name_or_num;
		}

		if (!is_array($pa_options)) { $pa_options = array(); }

		$vb_is_combo_key_relation = false; // indicates relation is via table_num/row_id combination key
		
		$vs_subject_table_name = $this->tableName();
		$vs_item_rel_table_name = $vs_rel_item_table_name = null;
		switch(sizeof($va_path = array_keys(Datamodel::getPath($vs_subject_table_name, $vs_related_table_name)))) {
			case 3:
				$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
				$vs_item_rel_table_name = $t_item_rel->tableName();
				
				$t_rel_item = Datamodel::getInstanceByTableName($va_path[2]);
				$vs_rel_item_table_name = $t_rel_item->tableName();
				
				$vs_key = $t_item_rel->primaryKey(); //'relation_id';
				break;
			case 2:
				$t_item_rel = $this->isRelationship() ? $this : null;
				$vs_item_rel_table_name = $t_item_rel ? $t_item_rel->tableName() : null;
				
				$t_rel_item = Datamodel::getInstanceByTableName($va_path[1]);
				$vs_rel_item_table_name = $t_rel_item->tableName();
				
				$vs_key = $t_rel_item->primaryKey();
				break;
			default:
				// is this related with row_id/table_num combo?
				if (
					($t_rel_item = Datamodel::getInstanceByTableName($vs_related_table_name))
					&&
					$t_rel_item->hasField('table_num') && $t_rel_item->hasField('row_id')
				) {
					$vs_key = $t_rel_item->primaryKey();
					$vs_rel_item_table_name = $t_rel_item->tableName();
					
					$vb_is_combo_key_relation = true;
					$va_path = array($vs_subject_table_name, $vs_rel_item_table_name);
				} else {
					// bad related table
					return null;
				}
				break;
		}

		// check for self relationship
		$vb_self_relationship = false;
		if($vs_subject_table_name == $vs_related_table_name) {
			$vb_self_relationship = true;
			$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
			$vs_item_rel_table_name = $t_item_rel->tableName();
			
			$t_rel_item = Datamodel::getInstanceByTableName($va_path[0]);
			$vs_rel_item_table_name = $t_rel_item->tableName();
		}

		$va_wheres = array();
		$va_selects = array();
		$va_joins_post_add = array();

		$vs_related_table = $vs_rel_item_table_name;
		if ($t_rel_item->hasField('type_id')) { $va_selects[] = "{$vs_related_table}.type_id item_type_id"; }
		if ($t_rel_item->hasField('source_id')) { $va_selects[] = "{$vs_related_table}.source_id item_source_id"; }

		// TODO: get these field names from models
		if (($t_tmp = $t_item_rel) || ($t_rel_item->isRelationship() && ($t_tmp = $t_rel_item))) {
			//define table names
			$vs_linking_table = $t_tmp->tableName();

			$va_selects[] = "{$vs_related_table}.".$t_rel_item->primaryKey();

			// include dates in returned data
			if ($t_tmp->hasField('effective_date')) {
				$va_selects[] = $vs_linking_table.'.sdatetime';
				$va_selects[] = $vs_linking_table.'.edatetime';

				$vb_uses_effective_dates = true;
			}

			if ($t_rel_item->hasField('is_enabled')) {
				$va_selects[] = "{$vs_related_table}.is_enabled";
			}


			if ($t_tmp->hasField('type_id')) {
				$va_selects[] = $vs_linking_table.'.type_id relationship_type_id';

				require_once(__CA_MODELS_DIR__.'/ca_relationship_types.php');
				$t_rel = new ca_relationship_types();

				$vb_uses_relationship_types = true;
			}

			// limit related items to a specific type
			if ($vb_uses_relationship_types && isset($pa_options['restrictToRelationshipTypes']) && $pa_options['restrictToRelationshipTypes']) {
				if (!is_array($pa_options['restrictToRelationshipTypes'])) {
					$pa_options['restrictToRelationshipTypes'] = array($pa_options['restrictToRelationshipTypes']);
				}

				if (sizeof($pa_options['restrictToRelationshipTypes'])) {
					$va_rel_types = array();
					foreach($pa_options['restrictToRelationshipTypes'] as $vm_type) {
						if (!$vm_type) { continue; }
						if (!($vn_type_id = $t_rel->getRelationshipTypeID($vs_linking_table, $vm_type))) {
							$vn_type_id = (int)$vm_type;
						}
						if ($vn_type_id > 0) {
							$va_rel_types[] = $vn_type_id;
							if (is_array($va_children = $t_rel->getHierarchyChildren($vn_type_id, array('idsOnly' => true)))) {
								$va_rel_types = array_merge($va_rel_types, $va_children);
							}
						}
					}

					if (sizeof($va_rel_types)) {
						$va_wheres[] = '('.$vs_linking_table.'.type_id IN ('.join(',', $va_rel_types).'))';
					}
				}
			}

			if ($vb_uses_relationship_types && isset($pa_options['excludeRelationshipTypes']) && $pa_options['excludeRelationshipTypes']) {
				if (!is_array($pa_options['excludeRelationshipTypes'])) {
					$pa_options['excludeRelationshipTypes'] = array($pa_options['excludeRelationshipTypes']);
				}

				if (sizeof($pa_options['excludeRelationshipTypes'])) {
					$va_rel_types = array();
					foreach($pa_options['excludeRelationshipTypes'] as $vm_type) {
						if ($vn_type_id = $t_rel->getRelationshipTypeID($vs_linking_table, $vm_type)) {
							$va_rel_types[] = $vn_type_id;
							if (is_array($va_children = $t_rel->getHierarchyChildren($vn_type_id, array('idsOnly' => true)))) {
								$va_rel_types = array_merge($va_rel_types, $va_children);
							}
						}
					}

					if (sizeof($va_rel_types)) {
						$va_wheres[] = '('.$vs_linking_table.'.type_id NOT IN ('.join(',', $va_rel_types).'))';
					}
				}
			}
		}

		// limit related items to a specific type
		$va_type_ids = caMergeTypeRestrictionLists($t_rel_item, $pa_options);

		if (is_array($va_type_ids) && (sizeof($va_type_ids) > 0)) {
			$va_wheres[] = "({$vs_related_table}.type_id IN (".join(',', $va_type_ids).')'.($t_rel_item->getFieldInfo('type_id', 'IS_NULL') ? " OR ({$vs_related_table}.type_id IS NULL)" : '').')';
		}

		$va_source_ids = caMergeSourceRestrictionLists($t_rel_item, $pa_options);
		if (method_exists($t_rel_item, "getSourceFieldName") && ($vs_source_id_fld = $t_rel_item->getSourceFieldName()) && is_array($va_source_ids) && (sizeof($va_source_ids) > 0)) {
			$va_wheres[] = "({$vs_related_table}.{$vs_source_id_fld} IN (".join(',', $va_source_ids)."))";
		}

		if (isset($pa_options['excludeType']) && $pa_options['excludeType']) {
			if (!isset($pa_options['excludeTypes']) || !is_array($pa_options['excludeTypes'])) {
				$pa_options['excludeTypes'] = array();
			}
			$pa_options['excludeTypes'][] = $pa_options['excludeType'];
		}

		if (isset($pa_options['excludeTypes']) && is_array($pa_options['excludeTypes'])) {
			$va_type_ids = caMakeTypeIDList($vs_related_table, $pa_options['excludeTypes']);

			if (is_array($va_type_ids) && (sizeof($va_type_ids) > 0)) {
				$va_wheres[] = "({$vs_related_table}.type_id NOT IN (".join(',', $va_type_ids)."))";
			}
		}

		if ($this->getAppConfig()->get('perform_item_level_access_checking')) {
			$t_user = new ca_users($vn_user_id, true);
			if (is_array($va_groups = $t_user->getUserGroups()) && sizeof($va_groups)) {
				$va_group_ids = array_keys($va_groups);
			} else {
				$va_group_ids = array();
			}

			// Join to limit what browse table items are used to generate facet
			$va_joins_post_add[] = 'LEFT JOIN ca_acl ON '.$vs_related_table_name.'.'.$t_rel_item->primaryKey().' = ca_acl.row_id AND ca_acl.table_num = '.$t_rel_item->tableNum()."\n";
			$va_wheres[] = "(
				((
					(ca_acl.user_id = ".(int)$vn_user_id.")
					".((sizeof($va_group_ids) > 0) ? "OR
					(ca_acl.group_id IN (".join(",", $va_group_ids)."))" : "")."
					OR
					(ca_acl.user_id IS NULL and ca_acl.group_id IS NULL)
				) AND ca_acl.access >= ".__CA_ACL_READONLY_ACCESS__.")
				".(($vb_show_if_no_acl) ? "OR ca_acl.acl_id IS NULL" : "")."
			)";
		}

		if (is_array($pa_get_where)) {
			foreach($pa_get_where as $vs_fld => $vm_val) {
				if ($t_rel_item->hasField($vs_fld)) {
					$va_wheres[] = "({$vs_related_table_name}.{$vs_fld} = ".(!is_numeric($vm_val) ? "'".$this->getDb()->escape($vm_val)."'": $vm_val).")";
				}
			}
		}

		if ($vs_idno_fld = $t_rel_item->getProperty('ID_NUMBERING_ID_FIELD')) { $va_selects[] = "{$vs_related_table}.{$vs_idno_fld}"; }
		if ($vs_idno_sort_fld = $t_rel_item->getProperty('ID_NUMBERING_SORT_FIELD')) { $va_selects[] = "{$vs_related_table}.{$vs_idno_sort_fld}"; }

		$va_selects[] = $va_path[1].'.'.$vs_key;

		if (isset($pa_options['fields']) && is_array($pa_options['fields'])) {
			$va_selects = array_merge($va_selects, $pa_options['fields']);
		}

		// if related item is labelable then include the label table in the query as well
		$vs_label_display_field = null;
		if (method_exists($t_rel_item, "getLabelTableName") && (!isset($pa_options['dontReturnLabels']) || !$pa_options['dontReturnLabels'])) {
			if($vs_label_table_name = $t_rel_item->getLabelTableName()) {           // make sure it actually has a label table...
				$va_path[] = $vs_label_table_name;
				$t_rel_item_label = Datamodel::getInstanceByTableName($vs_label_table_name);
				$vs_label_display_field = $t_rel_item_label->getDisplayField();

				if($pb_return_labels_as_array || (is_array($pa_sort_fields) && sizeof($pa_sort_fields))) {
					$va_selects[] = $vs_label_table_name.'.*';
				} else {
					$va_selects[] = $vs_label_table_name.'.'.$vs_label_display_field;
					$va_selects[] = $vs_label_table_name.'.locale_id';

					if ($t_rel_item_label->hasField('surname')) {	// hack to include fields we need to sort entity labels properly
						$va_selects[] = $vs_label_table_name.'.surname';
						$va_selects[] = $vs_label_table_name.'.forename';
					}
				}

				if ($t_rel_item_label->hasField('is_preferred') && (!isset($pa_options['returnNonPreferredLabels']) || !$pa_options['returnNonPreferredLabels'])) {
					$va_wheres[] = "(".$vs_label_table_name.'.is_preferred = 1)';
				}
			}
		}

		// return source info in returned data
		if ($t_item_rel && $t_item_rel->hasField('source_info')) {
			$va_selects[] = $vs_linking_table.'.source_info';
		}

		if (isset($pa_options['checkAccess']) && is_array($pa_options['checkAccess']) && sizeof($pa_options['checkAccess']) && $t_rel_item->hasField('access')) {
			$va_wheres[] = "({$vs_related_table}.access IN (".join(',', $pa_options['checkAccess'])."))";
		}

		if ((!isset($pa_options['showDeleted']) || !$pa_options['showDeleted']) && $t_rel_item->hasField('deleted')) {
			$va_wheres[] = "({$vs_related_table}.deleted = 0)";
		}
		
		if (($va_criteria = (isset($pa_options['criteria']) ? $pa_options['criteria'] : null)) && (is_array($va_criteria)) && (sizeof($va_criteria))) {
			$va_wheres[] = "(".join(" AND ", $va_criteria).")"; 
		}

		if($vb_self_relationship) {
			//
			// START - traverse self relation
			//
			$va_rel_info = Datamodel::getRelationships($va_path[0], $va_path[1]);
			if ($vs_label_table_name) {
				$va_label_rel_info = Datamodel::getRelationships($va_path[0], $vs_label_table_name);
			}
	
			$va_rels = $va_rels_by_date = [];

			$vn_i = 0;
			foreach($va_rel_info[$va_path[0]][$va_path[1]] as $va_possible_keys) {
				$va_joins = array();
				$va_joins[] = "INNER JOIN ".$va_path[1]." ON ".$va_path[1].'.'.$va_possible_keys[1].' = '.$va_path[0].'.'.$va_possible_keys[0]."\n";

				if ($vs_label_table_name) {
					$va_joins[] = "INNER JOIN ".$vs_label_table_name." ON ".$vs_label_table_name.'.'.$va_label_rel_info[$va_path[0]][$vs_label_table_name][0][1].' = '.$va_path[0].'.'.$va_label_rel_info[$va_path[0]][$vs_label_table_name][0][0]."\n";
				}

				$vs_other_field = ($vn_i == 0) ? $va_rel_info[$va_path[0]][$va_path[1]][1][1] : $va_rel_info[$va_path[0]][$va_path[1]][0][1];
				$vs_direction =  (preg_match('!left!', $vs_other_field)) ? 'ltor' : 'rtol';

				$va_selects['row_id'] = $va_path[1].'.'.$vs_other_field.' AS row_id';

				$vs_order_by = '';
				$vs_sort_fld = '';
				if ($t_item_rel && $t_item_rel->hasField('rank')) {
					$vs_order_by = " ORDER BY {$vs_item_rel_table_name}.rank";
					$vs_sort_fld = 'rank';
					$va_selects[] = "{$vs_item_rel_table_name}.rank";
				} else {
					if ($t_rel_item && ($vs_sort = $t_rel_item->getProperty('ID_NUMBERING_SORT_FIELD'))) {
						$vs_order_by = " ORDER BY {$vs_related_table}.{$vs_sort}";
						$vs_sort_fld = $vs_sort;
						$va_selects[] = "{$vs_related_table}.{$vs_sort}";
					}
				}

				$vs_sql = "
					SELECT ".join(', ', $va_selects)."
					FROM ".$va_path[0]."
					".join("\n", array_merge($va_joins, $va_joins_post_add))."
					WHERE
						".join(' AND ', array_merge($va_wheres, array('('.$va_path[1].'.'.$vs_other_field .' IN ('.join(',', $pa_row_ids).'))')))."
					{$vs_order_by}";

				$qr_res = $o_db->query($vs_sql);
				
				if (!is_null($pn_count)) { $pn_count = $qr_res->numRows(); }

				if ($vb_uses_relationship_types) { $va_rel_types = $t_rel->getRelationshipInfo($va_path[1]); }
				$vn_c = 0;
				if ($pn_start > 0) { $qr_res->seek($pn_start); }
				while($qr_res->nextRow()) {
					if ($vn_c >= $pn_limit) { break; }
					
					if (is_array($pa_primary_ids) && is_array($pa_primary_ids[$vs_related_table])) {
						if (in_array($qr_res->get($vs_key), $pa_primary_ids[$vs_related_table])) { continue; }
					}
					
					if ($ps_return_as !== 'data') {
						$va_rels[] = $qr_res->get($t_rel_item->primaryKey());
						continue;
					}
					
					$va_row = $qr_res->getRow();
					$vn_id = $va_row[$vs_key].'/'.$va_row['row_id'];
					$vs_sort_key = $qr_res->get($vs_sort_fld);

					$vs_display_label = $va_row[$vs_label_display_field];

					if (!$va_rels[$vs_sort_key][$vn_id]) {
						$va_rels[$vs_sort_key][$vn_id] = $qr_res->getRow();
					}

					if ($vb_uses_effective_dates) {	// return effective dates as display/parse-able text
						if ($va_rels[$vs_sort_key][$vn_id]['sdatetime'] || $va_rels[$vs_sort_key][$vn_id]['edatetime']) {
							$o_tep->setHistoricTimestamps($va_rels[$vs_sort_key][$vn_id]['sdatetime'], $va_rels[$vs_sort_key][$vn_id]['edatetime']);
							$va_rels[$vs_sort_key][$vn_id]['effective_date'] = $o_tep->getText();
						}
					}

					$vn_locale_id = $qr_res->get('locale_id');
					if ($pb_use_locale_codes) {
						$va_rels[$vs_sort_key][$vn_id]['locale_id'] = $vn_locale_id = $t_locale->localeIDToCode($vn_locale_id);
					}

					$va_rels[$vs_sort_key][$vn_id]['labels'][$vn_locale_id] =  ($pb_return_labels_as_array) ? $va_row : $vs_display_label;
					$va_rels[$vs_sort_key][$vn_id]['_key'] = $vs_key;
					$va_rels[$vs_sort_key][$vn_id]['direction'] = $vs_direction;

					$vn_c++;
					if ($vb_uses_relationship_types) {
						$va_rels[$vs_sort_key][$vn_id]['relationship_typename'] = ($vs_direction == 'ltor') ? $va_rel_types[$va_row['relationship_type_id']]['typename'] : $va_rel_types[$va_row['relationship_type_id']]['typename_reverse'];
						$va_rels[$vs_sort_key][$vn_id]['relationship_type_code'] = $va_rel_types[$va_row['relationship_type_id']]['type_code'];
					}

					//
					// Return data in an arrangement more convenient for the data importer 
					//
					if ($pb_group_fields) {
						$vs_rel_pk = $t_rel_item->primaryKey();
						if ($t_rel_item_label) {
							foreach($t_rel_item_label->getFormFields() as $vs_field => $va_field_info) {
								if (!isset($va_rels[$vs_sort_key][$vn_id][$vs_field]) || ($vs_field == $vs_rel_pk)) { continue; }
								$va_rels[$vs_sort_key][$vn_id]['preferred_labels'][$vs_field] = $va_rels[$vs_sort_key][$vn_id][$vs_field];
								unset($va_rels[$vs_sort_key][$vn_id][$vs_field]);
							}
						}
						foreach($t_rel_item->getFormFields() as $vs_field => $va_field_info) {
							if (!isset($va_rels[$vs_sort_key][$vn_id][$vs_field]) || ($vs_field == $vs_rel_pk)) { continue; }
							$va_rels[$vs_sort_key][$vn_id]['intrinsic'][$vs_field] = $va_rels[$vs_sort_key][$vn_id][$vs_field];
							unset($va_rel[$vs_sort_key][$vn_id][$vs_field]);
						}
						unset($va_rels[$vs_sort_key][$vn_id]['_key']);
						unset($va_rels[$vs_sort_key][$vn_id]['row_id']);
					}
					
					// filter for current?
					if($pb_show_current_only && $t_item_rel) {
						$qr_rels = caMakeSearchResult($t_item_rel->tableName(), [$qr_res->get($vs_key)]);
						
						while($qr_rels->nextHit()) {
							foreach($qr_rels->get($ps_current_date_bundle, ['returnAsArray' => true, 'sortable' => true]) as $vs_date) {
								$va_tmp = explode("/", $vs_date);
								if ($va_tmp[0] > $vn_current_date) { continue; } 	// skip future dates
								$va_rels_by_date[$vs_date.'/'.sprintf("%09d", $qr_rels->get($t_item_rel->tableName().".relation_id"))][$vs_sort_key][$vn_id] = $va_rels[$vs_sort_key][$vn_id];
							}
						}
					}
				}
				$vn_i++;
			}

			if($pb_show_current_only && $t_item_rel) {
				ksort($va_rels_by_date);
				$va_rels = array_pop($va_rels_by_date);
			}
			
			ksort($va_rels);	// sort by sort key... we'll remove the sort key in the next loop while we add the labels

			// Set 'label' entry - display label in current user's locale
			$va_sorted_rels = array();
			foreach($va_rels as $vs_sort_key => $va_rels_by_sort_key) {
				foreach($va_rels_by_sort_key as $vn_id => $va_rel) {
					$va_tmp = array(0 => $va_rel['labels']);
					$va_sorted_rels[$vn_id] = $va_rel;
					$va_values_filtered_by_locale = caExtractValuesByUserLocale($va_tmp);
					$va_sorted_rels[$vn_id]['label'] = array_shift($va_values_filtered_by_locale);
				}
			}
			$va_rels = $va_sorted_rels;

			//
			// END - traverse self relation
			//
		} else if (method_exists($this, 'isSelfRelationship') && $this->isSelfRelationship()) {
			//
			// START - from self relation itself (Eg. get related ca_objects from ca_objects_x_objects); in this case there are two possible paths (keys) to check, "left" and "right"
			//
			
			$pb_show_current_only = false;
			
			$va_wheres[] = "({$vs_subject_table_name}.".$this->primaryKey()." IN (".join(",", $pa_row_ids)."))";
			$vs_cur_table = array_shift($va_path);
			$vs_rel_table = array_shift($va_path);
			
			$va_rel_info = Datamodel::getRelationships($vs_cur_table, $vs_rel_table);

			$va_rels = array();
			foreach($va_rel_info[$vs_cur_table][$vs_rel_table] as $vn_i => $va_rel) {
				$va_joins = array(
					'INNER JOIN '.$vs_rel_table.' ON '.$vs_cur_table.'.'.$va_rel[0].' = '.$vs_rel_table.'.'.$va_rel[1]."\n"	
				);
				
				$vs_base_table = $vs_rel_table;
				foreach($va_path as $vs_join_table) {
					$va_label_rel_info = Datamodel::getRelationships($vs_base_table, $vs_join_table);
					$va_joins[] = 'INNER JOIN '.$vs_join_table.' ON '.$vs_base_table.'.'.$va_label_rel_info[$vs_base_table][$vs_join_table][0][0].' = '.$vs_join_table.'.'.$va_label_rel_info[$vs_base_table][$vs_join_table][0][1]."\n";
					$vs_base_table = $vs_join_table;
				}
				
				$va_selects[] = $vs_subject_table_name.'.'.$this->primaryKey().' AS row_id';

                $vb_use_is_primary = false;
                if ($t_item_rel && $t_item_rel->hasField('is_primary')) {
                    $va_selects[] = $t_item_rel->tableName().'.is_primary';
                    $vb_use_is_primary = true;
                }

				$vs_order_by = '';
				if ($t_item_rel && $t_item_rel->hasField('rank')) {
					$vs_order_by = " ORDER BY {$vs_item_rel_table_name}.rank";
					$va_selects[] = $t_item_rel->tableName().'.rank';
				} else {
					if ($t_rel_item && ($vs_sort = $t_rel_item->getProperty('ID_NUMBERING_SORT_FIELD'))) {
						$vs_order_by = " ORDER BY {$vs_related_table}.{$vs_sort}";
						$va_selects[] = "{$vs_related_table}.{$vs_sort}";
					}
				}

				$vs_sql = "
					SELECT DISTINCT ".join(', ', $va_selects)."
					FROM {$vs_subject_table_name}
					".join("\n", array_merge($va_joins, $va_joins_post_add))."
					WHERE
						".join(' AND ', $va_wheres)."
					{$vs_order_by}
				";

				//print "<pre>$vs_sql</pre>\n";
				$qr_res = $o_db->query($vs_sql);
				
				if (!is_null($pn_count)) { $pn_count = $qr_res->numRows(); }
				
				if ($vb_uses_relationship_types)  {
					$va_rel_types = $t_rel->getRelationshipInfo($vs_item_rel_table_name);
					$vs_left_table = $t_item_rel->getLeftTableName();
					$vs_direction = ($vs_left_table == $vs_subject_table_name) ? 'ltor' : 'rtol';
				}
				
				$vn_c = 0;
				if ($pn_start > 0) { $qr_res->seek($pn_start); }
				while($qr_res->nextRow()) {
					if ($vn_c >= $pn_limit) { break; }
					
					if (is_array($pa_primary_ids) && is_array($pa_primary_ids[$vs_related_table])) {
						if (in_array($qr_res->get($vs_key), $pa_primary_ids[$vs_related_table])) { continue; }
					}
					
					if ($ps_return_as !== 'data') {
						$va_rels[] = $qr_res->get($t_rel_item->primaryKey());
						continue;
					}

					$va_row = $qr_res->getRow();
					$vs_v = $va_row['row_id'].'/'.$va_row[$vs_key];

					$vs_display_label = $va_row[$vs_label_display_field];

					if (!isset($va_rels[$vs_v]) || !$va_rels[$vs_v]) {
						$va_rels[$vs_v] = $va_row;
					}

					if ($vb_uses_effective_dates) {	// return effective dates as display/parse-able text
						if ($va_rels[$vs_v]['sdatetime'] || $va_rels[$vs_v]['edatetime']) {
							$o_tep->setHistoricTimestamps($va_rels[$vs_v]['sdatetime'], $va_rels[$vs_v]['edatetime']);
							$va_rels[$vs_v]['effective_date'] = $o_tep->getText();
						}
					}

					$vn_locale_id = $qr_res->get('locale_id');

					if ($pb_use_locale_codes) {
						$va_rels[$vs_v]['locale_id'] = $vn_locale_id = $t_locale->localeIDToCode($vn_locale_id);
					}

					$va_rels[$vs_v]['labels'][$vn_locale_id] =  ($pb_return_labels_as_array) ? $va_row : $vs_display_label;

					$va_rels[$vs_v]['_key'] = $vs_key;
					$va_rels[$vs_v]['direction'] = $vs_direction;
					
                    if ($vb_use_is_primary) {
                        $va_rels_for_id[$vs_v]['is_primary'] = $qr_res->get('is_primary');
                    }

					$vn_c++;
					if ($vb_uses_relationship_types) {
						$va_rels[$vs_v]['relationship_typename'] = ($vs_direction == 'ltor') ? $va_rel_types[$va_row['relationship_type_id']]['typename'] : $va_rel_types[$va_row['relationship_type_id']]['typename_reverse'];
						$va_rels[$vs_v]['relationship_type_code'] = $va_rel_types[$va_row['relationship_type_id']]['type_code'];
					}

					if ($pb_group_fields) {
						$vs_rel_pk = $t_rel_item->primaryKey();
						if ($t_rel_item_label) {
							foreach($t_rel_item_label->getFormFields() as $vs_field => $va_field_info) {
								if (!isset($va_rels[$vs_v][$vs_field]) || ($vs_field == $vs_rel_pk)) { continue; }
								$va_rels[$vs_v]['preferred_labels'][$vs_field] = $va_rels[$vs_v][$vs_field];
								unset($va_rels[$vs_v][$vs_field]);
							}
						}
						foreach($t_rel_item->getFormFields() as $vs_field => $va_field_info) {
							if (!isset($va_rels[$vs_v][$vs_field]) || ($vs_field == $vs_rel_pk)) { continue; }
							$va_rels[$vs_v]['intrinsic'][$vs_field] = $va_rels[$vs_v][$vs_field];
							unset($va_rels[$vs_v][$vs_field]);
						}
						unset($va_rels[$vs_v]['_key']);
						unset($va_rels[$vs_v]['row_id']);
					}
				}

				if ($ps_return_as === 'data') {
					// Set 'label' entry - display label in current user's locale
					foreach($va_rels as $vs_v => $va_rel) {
						$va_tmp = array(0 => $va_rel['labels']);
						$va_tmp2 = caExtractValuesByUserLocale($va_tmp);
						$va_rels[$vs_v]['label'] = array_shift($va_tmp2);
					}
				}
			}
			
			//
			// END - from self relation itself
			//
		} else {
			//
			// BEGIN - non-self relation
			//
			$va_wheres[] = "({$vs_subject_table_name}.".$this->primaryKey()." IN (".join(",", $pa_row_ids)."))";
			$vs_cur_table = array_shift($va_path);
			$va_joins = array();

			// Enforce restrict_to_lists for related list items
			if (($vs_related_table_name == 'ca_list_items') && is_array($pa_options['restrictToLists'])) {
				$va_list_ids = array();
				foreach($pa_options['restrictToLists'] as $vm_list) {
					if ($vn_list_id = ca_lists::getListID($vm_list)) { $va_list_ids[] = $vn_list_id; }
				}
				if (sizeof($va_list_ids)) {
					$va_wheres[] = "(ca_list_items.list_id IN (".join(",", $va_list_ids)."))";
				}
			}

			if ($vb_is_combo_key_relation) {
				$va_joins = array("INNER JOIN {$vs_related_table_name} ON {$vs_related_table_name}.row_id = ".$this->primaryKey(true)." AND {$vs_related_table_name}.table_num = ".$this->tableNum());
				if(method_exists($t_rel_item, "getLabelTableInstance") && ($t_rel_label = $t_rel_item->getLabelTableInstance())) {
				    $vs_related_label_table_name = $t_rel_label->tableName();
				    $vs_rel_pk = $t_rel_item->primaryKey();
				    $va_joins[] = "INNER JOIN {$vs_related_label_table_name} ON {$vs_related_label_table_name}.{$vs_rel_pk} = {$vs_related_table_name}.{$vs_rel_pk}";
			    }
			} else {
				foreach($va_path as $vs_join_table) {
					$va_rel_info = Datamodel::getRelationships($vs_cur_table, $vs_join_table);
					$vs_join = 'INNER JOIN '.$vs_join_table.' ON ';
				
					$va_tmp = array();
					foreach($va_rel_info[$vs_cur_table][$vs_join_table] as $vn_i => $va_rel) {
						$va_tmp[] = $vs_cur_table.".".$va_rel_info[$vs_cur_table][$vs_join_table][$vn_i][0].' = '.$vs_join_table.'.'.$va_rel_info[$vs_cur_table][$vs_join_table][$vn_i][1]."\n";
					}
					$va_joins[] = $vs_join.join(' OR ', $va_tmp);
					$vs_cur_table = $vs_join_table;
				}
				
				
			
                if (method_exists($t_rel_item, 'isRelationship') && $t_rel_item->isRelationship()) {
                    if(is_array($pa_options['restrictToTypes']) && sizeof($pa_options['restrictToTypes'])) {
                        $va_rels = Datamodel::getManyToOneRelations($t_rel_item->tableName());

                        foreach($va_rels as $vs_rel_pk => $va_rel_info) {
                            if ($va_rel_info['one_table'] != $this->tableName()) {
                                $va_type_ids = caMakeTypeIDList($va_rel_info['one_table'], $pa_options['restrictToTypes']);
                    
                                if (is_array($va_type_ids) && sizeof($va_type_ids)) { 
                                    $va_joins[] = "INNER JOIN {$va_rel_info['one_table']} AS r ON r.{$va_rel_info['one_table_field']} = ".$t_rel_item->tableName().".{$vs_rel_pk}";
                                    $va_wheres[] = "(r.type_id IN (".join(",", $va_type_ids)."))";
                                }
                                break;
                            }
                        }
                    }elseif(is_array($pa_options['excludeTypes']) && sizeof($pa_options['excludeTypes'])) {
                        $va_rels = Datamodel::getManyToOneRelations($t_rel_item->tableName());

                        foreach($va_rels as $vs_rel_pk => $va_rel_info) {
                            if ($va_rel_info['one_table'] != $this->tableName()) {
                                $va_type_ids = caMakeTypeIDList($va_rel_info['one_table'], $pa_options['excludeTypes']);
                                
                                if (is_array($va_type_ids) && sizeof($va_type_ids)) { 
                                    $va_joins[] = "INNER JOIN {$va_rel_info['one_table']} AS r ON r.{$va_rel_info['one_table_field']} = ".$t_rel_item->tableName().".{$vs_rel_pk}";
                                    $va_wheres[] = "(r.type_id NOT IN (".join(",", $va_type_ids)."))";
                                }
                                break;
                            }
                        }
                    }
                }
			}

			// If we're getting ca_set_items, we have to rename the intrinsic row_id field because the pk is named row_id below. Hence, this hack.
			if($vs_related_table_name == 'ca_set_items') {
				$va_selects[] = 'ca_set_items.row_id AS record_id';
			}
			
			$vb_use_is_primary = false;
			if ($t_item_rel && $t_item_rel->hasField('is_primary')) {
			    $va_selects[] = $t_item_rel->tableName().'.is_primary';
			    $vb_use_is_primary = true;
			}

			$va_selects[] = $vs_subject_table_name.'.'.$this->primaryKey().' AS row_id';

			$vs_order_by = '';
			if ($t_item_rel && $t_item_rel->hasField('rank')) {
				$vs_order_by = " ORDER BY {$vs_item_rel_table_name}.rank";
				$va_selects[] = $t_item_rel->tableName().'.rank';
			} else {
				if ($t_rel_item && ($vs_sort = $t_rel_item->getProperty('ID_NUMBERING_SORT_FIELD'))) {
					$vs_order_by = " ORDER BY {$vs_related_table}.{$vs_sort}";
					$va_selects[] = "{$vs_related_table}.{$vs_sort}";
				}
			}
			
			$vs_sql = "
				SELECT DISTINCT ".join(', ', $va_selects)."
				FROM {$vs_subject_table_name}
				".join("\n", array_merge($va_joins, $va_joins_post_add))."
				WHERE
					".join(' AND ', $va_wheres)."
				{$vs_order_by}
			";
			
			$qr_res = $o_db->query($vs_sql);
			
			if (!is_null($pn_count)) { $pn_count = $qr_res->numRows(); }
			
			if ($vb_uses_relationship_types)  {
				$va_rel_types = $t_rel->getRelationshipInfo($t_tmp->tableName());
				if(method_exists($t_tmp, 'getLeftTableName')) {
					$vs_left_table = $t_tmp->getLeftTableName();
					$vs_direction = ($vs_left_table == $vs_subject_table_name) ? 'ltor' : 'rtol';
				}
			}
			
			$va_rels = [];
			$va_rels_by_date = [];
			
			$vn_c = 0;
			if ($pn_start > 0) { $qr_res->seek($pn_start); }
			$va_seen_row_ids = array();
			$va_relation_ids = $va_rels_for_id_by_date = [];
			while($qr_res->nextRow()) {
				$va_rels_for_id = [];
				if ($vn_c >= $pn_limit) { break; }
				
				if (is_array($pa_primary_ids) && is_array($pa_primary_ids[$vs_related_table])) {
					if (in_array($qr_res->get($vs_key), $pa_primary_ids[$vs_related_table])) { continue; }
				}
				
				//if ($ps_return_as !== 'data') {
				//	$va_rels_for_id[] = $qr_res->get($t_rel_item->primaryKey());
				//	continue;
				//}

				$va_row = $qr_res->getRow();
				$vs_v = (sizeof($va_path) <= 2) ? $va_row['row_id'].'/'.$va_row[$vs_key] : $va_row[$vs_key];

				$vs_display_label = $va_row[$vs_label_display_field];

				if (!isset($va_rels_for_id[$vs_v]) || !$va_rels_for_id[$vs_v]) {
					$va_rels_for_id[$vs_v] = $va_row;
				}

				if ($vb_uses_effective_dates) {	// return effective dates as display/parse-able text
					if ($va_rels_for_id[$vs_v]['sdatetime'] || $va_rels_for_id[$vs_v]['edatetime']) {
						$o_tep->setHistoricTimestamps($va_rels_for_id[$vs_v]['sdatetime'], $va_rels_for_id[$vs_v]['edatetime']);
						$va_rels_for_id[$vs_v]['effective_date'] = $o_tep->getText();
					}
				}

				$vn_locale_id = $qr_res->get('locale_id');
				if ($pb_use_locale_codes) {
					$va_rels_for_id[$vs_v]['locale_id'] = $vn_locale_id = $t_locale->localeIDToCode($vn_locale_id);
				}

				$va_rels_for_id[$vs_v]['labels'][$vn_locale_id] =  ($pb_return_labels_as_array) ? $va_row : $vs_display_label;

				$va_rels_for_id[$vs_v]['_key'] = $vs_key;
				$va_rels_for_id[$vs_v]['direction'] = $vs_direction;
				
				if ($vb_use_is_primary) {
				    $va_rels_for_id[$vs_v]['is_primary'] = $qr_res->get('is_primary');
                }
                
				$vn_c++;
				if ($vb_uses_relationship_types) {
					$va_rels_for_id[$vs_v]['relationship_typename'] = ($vs_direction == 'ltor') ? $va_rel_types[$va_row['relationship_type_id']]['typename'] : $va_rel_types[$va_row['relationship_type_id']]['typename_reverse'];
					$va_rels_for_id[$vs_v]['relationship_type_code'] = $va_rel_types[$va_row['relationship_type_id']]['type_code'];
				}

				if ($pb_group_fields) {
					$vs_rel_pk = $t_rel_item->primaryKey();
					if ($t_rel_item_label) {
						foreach($t_rel_item_label->getFormFields() as $vs_field => $va_field_info) {
							if (!isset($va_rels_for_id[$vs_v][$vs_field]) || ($vs_field == $vs_rel_pk)) { continue; }
							$va_rels_for_id[$vs_v]['preferred_labels'][$vs_field] = $va_rels_for_id[$vs_v][$vs_field];
							unset($va_rels_for_id[$vs_v][$vs_field]);
						}
					}
					foreach($t_rel_item->getFormFields() as $vs_field => $va_field_info) {
						if (!isset($va_rels_for_id[$vs_v][$vs_field]) || ($vs_field == $vs_rel_pk)) { continue; }
						$va_rels_for_id[$vs_v]['intrinsic'][$vs_field] = $va_rels_for_id[$vs_v][$vs_field];
						unset($va_rels_for_id[$vs_v][$vs_field]);
					}
					unset($va_rels_for_id[$vs_v]['_key']);
					unset($va_rels_for_id[$vs_v]['row_id']);
				}
							
				// filter for current?
				if($pb_show_current_only && $t_item_rel) {
				    if ($this->isRelationship()) {
				        $k = $this->tableName().".".(($this->getLeftTableFieldName() == $vs_key) ? $this->getRightTableFieldName() : $this->getLeftTableFieldName());
				        $t = $t_rel_item->tableName();
				        $id = $qr_res->get($t_rel_item->primaryKey());
				    } else {
				        $k = $this->primaryKey(true);
				        $t = $t_item_rel->tableName();
				        $id = $qr_res->get($vs_key);
				    }
				    $cd = $ps_current_date_bundle;
				    if ($cd == 'effective_date') { $cd = $t_item_rel->tableName().".{$cd}"; }
				    
					$qr_rels = caMakeSearchResult($t, [$id]);
					while($qr_rels->nextHit()) {
						foreach($d= $qr_rels->get($cd, ['returnAsArray' => true, 'sortable' => true]) as $vs_date) {
							$va_tmp = explode("/", $vs_date);
							if ($va_tmp[0] > $vn_current_date) { continue; } 	// skip future dates
							$va_rels_for_id_by_date[$qr_rels->get($k)][$vs_date.'/'.sprintf("%09d", $qr_rels->get($t_item_rel->tableName().".relation_id"))][$vs_v] = $va_rels_for_id[$vs_v];
						}
					}
				}
				$va_rels = array_replace($va_rels, $va_rels_for_id);
				
				$va_seen_row_ids[$va_row['row_id']] = true;
			}
			
			if($pb_show_current_only && $t_item_rel) {
				$va_rels_for_id = [];
				foreach($va_rels_for_id_by_date as $vn_id => $va_by_date) {
					ksort($va_by_date);
					if (sizeof($va_by_date)) { 
						foreach(array_pop($va_by_date) as $vs_v => $va_rel) {
							$va_rels_for_id[$vs_v] = $va_rel;
						}
						
						//break;
					}
				}
				$va_rels = $va_rels_for_id;
			}
							
			if ($ps_return_as !== 'data') {
				$va_rels = caExtractArrayValuesFromArrayOfArrays($va_rels, $t_rel_item->primaryKey());
			}
			

			if ($ps_return_as === 'data') {
				// Set 'label' entry - display label in current user's locale
				foreach($va_rels as $vs_v => $va_rel) {
					$va_tmp = array(0 => $va_rel['labels']);
					$va_tmp2 = caExtractValuesByUserLocale($va_tmp);
					$va_rels[$vs_v]['label'] = array_shift($va_tmp2);
				}
			} 
			
			//
			// END - non-self relation
			//
		}
		if ($pb_show_current_only) {
		    $va_rels = array_slice($va_rels, sizeof($va_rels)-1, 1);
		}

		// Apply restrictToBundleValues
		$va_filters = isset($pa_options['restrictToBundleValues']) ? $pa_options['restrictToBundleValues'] : null;
		if(is_array($va_filters) && (sizeof($va_filters)>0)) {
			foreach($va_rels as $vn_pk => $va_related_item) {
				foreach($va_filters as $vs_filter => $va_filter_vals) {
					if(!$vs_filter) { continue; }
					if (!is_array($va_filter_vals)) { $va_filter_vals = array($va_filter_vals); }

					foreach($va_filter_vals as $vn_index => $vs_filter_val) {
						// is value a list attribute idno?
						$va_tmp = explode('.',$vs_filter);
						$vs_element = array_pop($va_tmp);
						if (!is_numeric($vs_filter_val) && (($t_element = ca_metadata_elements::getInstance($vs_element)) && ($t_element->get('datatype') == 3))) {
							$va_filter_vals[$vn_index] = caGetListItemID($t_element->get('list_id'), $vs_filter_val);
						}
					}

					$t_rel_item->load($va_related_item[$t_rel_item->primaryKey()]);
					$va_filter_values = $t_rel_item->get($vs_filter, array('returnAsArray' => true, 'alwaysReturnItemID' => true));

					$vb_keep = false;
					if (is_array($va_filter_values)) {
						foreach($va_filter_values as $vm_filtered_val) {
							if(!is_array($vm_filtered_val)) { $vm_filtered_val = array($vm_filtered_val); }

							foreach($vm_filtered_val as $vs_val) {
								if (in_array($vs_val, $va_filter_vals)) {	// one match is enough to keep it
									$vb_keep = true;
								}
							}
						}
					}

					if(!$vb_keep) {
						unset($va_rels[$vn_pk]);
					}
				}
			}
		}

		//
		// Sort on fields if specified
		//
		if (is_array($pa_sort_fields) && sizeof($pa_sort_fields) && sizeof($va_rels)) {
			$va_ids = $va_ids_to_rel_ids = array();
			$vs_rel_pk = $t_rel_item->primaryKey();
			foreach($va_rels as $vn_i => $va_rel) {
				if(is_array($va_rel)) {
					$va_ids[$vn_i] = $va_rel[$vs_rel_pk];
				} else {
					$va_ids[$vn_i] = $va_rel;
				}
				$va_ids_to_rel_ids[$va_rel[$vs_rel_pk]][] = $vn_i;
			}
			if (sizeof($va_ids) > 0) {
				$qr_sort = caMakeSearchResult($vs_related_table_name, array_values($va_ids), array('sort' => $pa_sort_fields, 'sortDirection' => $ps_sort_direction));
				
				$va_rels_sorted = array();
				
				$vs_rel_pk_full = $t_rel_item->primaryKey(true);
				while($qr_sort->nextHit()) {
					foreach($va_ids_to_rel_ids[$qr_sort->get($vs_rel_pk_full)] as $vn_rel_id) {
						$va_rels_sorted[$vn_rel_id] = $va_rels[$vn_rel_id];
					}
				}
				$va_rels = $va_rels_sorted;
			}
		}
		
		switch($ps_return_as) {
			case 'firstmodelinstance':
				foreach($va_rels as $vn_id) {
					$o_instance = new $vs_related_table_name;
					if ($o_instance->load($vn_id)) {
						return $o_instance;
					}
				}
				return null;
				break;
			case 'modelinstances':
				$va_instances = array();
				foreach($va_rels as $vn_id) {
					$o_instance = new $vs_related_table_name;
					if ($o_instance->load($vn_id)) {
						$va_instances[] = $o_instance;
					}
				}
				return $va_instances;
				break;
			case 'firstid':
				if(sizeof($va_rels)) {
					return array_shift($va_rels);
				}
				return null;
				break;
			case 'count':
				return sizeof($va_rels);
				break;
			case 'searchresult':
				if (sizeof($va_rels) > 0) {
					return caMakeSearchResult($vs_related_table_name, $va_rels);
				}
				return null;
				break;
			default:
			case 'ids':
				return $va_rels;
				break;
		}
	}
}
