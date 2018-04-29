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
	 * @param string $ps_direction Optional direction specification for self-relationships (relationships linking two rows in the same table). Valid values are 'ltor' (left-to-right) and  'rtol' (right-to-left); the direction determines which "side" of the relationship the currently loaded row is on: 'ltor' puts the current row on the left side. For many self-relations the direction determines the nature and display text for the relationship.
	 * @param array $pa_options Array of additional options:
	 *		allowDuplicates = if set to true, attempts to add a relationship that already exists will succeed. Default is false - duplicate relationships will not be created.
	 *		setErrorOnDuplicate = if set to true, an error will be set if an attempt is made to add a duplicate relationship. Default is false - don't set error. addRelationship() will always return false when creation of a duplicate relationship fails, no matter how the setErrorOnDuplicate option is set.
	 * @return BaseRelationshipModel Loaded relationship model instance on success, false on error.
	 */
	public function addRelationship($pm_rel_table_name_or_num, $pn_rel_id, $pm_type_id=null, $ps_effective_date=null, $ps_source_info=null, $ps_direction=null, $pn_rank=null, $pa_options=null) {
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
	public function editRelationship($pm_rel_table_name_or_num, $pn_relation_id, $pn_rel_id, $pm_type_id=null, $ps_effective_date=null, $pa_source_info=null, $ps_direction=null, $pn_rank=null, $pa_options=null) {
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
	public function moveRelationships($pm_rel_table_name_or_num, $pn_to_id, $pa_options=null) {
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
	# ------------------------------------------------------
 	/**
 	 * Returns list of items in the specified table related to the currently loaded row or rows specified in options. This is a simplified version of
 	 * BundlableLabelableBaseModelWithAttributes::getRelatedItems() for models derived directly from BaseModel.
 	 * 
 	 * @param $pm_rel_table_name_or_num - the table name or table number of the item type you want to get a list of (eg. if you are calling this on an ca_item_comments instance passing 'ca_users' here will get you a list of users related to the comment)
 	 * @param $pa_options - array of options. Supported options are:
 	 *
 	 *		[Options controlling rows for which data is returned]
 	 *			row_ids = Array of primary key values to use when fetching related items. If omitted or set to a null value the 'row_id' option will be used. [Default is null]
 	 *			row_id = Primary key value to use when fetching related items. If omitted or set to a false value (null, false, 0) then the primary key value of the currently loaded row is used. [Default is currently loaded row]
 	 *			start = Zero-based index to begin return set at. [Default is 0]
 	 *			limit = Maximum number of related items to return. [Default is 1000]
 	 *			showDeleted = Return related items that have been deleted. [Default is false]
 	 *			primaryIDs = array of primary keys in related table to exclude from returned list of items. Array is keyed on table name for compatibility with the parameter as used in the caProcessTemplateForIDs() helper [Default is null - nothing is excluded].
 	 *			where = Restrict returned items to specified field values. The fields must be intrinsic and in the related table. This option can be useful when you want to efficiently fetch specific rows from a related table. Note that multiple fields/values are logically AND'ed together – all must match for a row to be returned - and that only equivalence is supported. [Default is null]			
 	 *			criteria = Restrict returned items using SQL criteria appended directly onto the query. Criteria is used as-is and must be compatible with the generated SQL query. [Default is null]
 	 *
 	 *		[Options controlling scope of data in return value]
 	 *			restrictToLists = Restrict returned items to those that are in one or more specified lists. This option is only relevant when fetching related ca_list_items. An array of list list_codes or list_ids may be specified. [Default is null]
 	 * 			fields = array of fields (in table.fieldname format) to include in returned data. [Default is null]
 	 *			idsOnly = Return one-dimensional array of related primary key values only. [Default is false]
 	 *
 	 *		[Options controlling format of data in return value]
 	 *			sort = Array list of bundles to sort returned values on. The sortable bundle specifiers are fields with or without tablename. Only those fields returned for the related tables (intrinsics) are sortable. [Default is null]
	 *			sortDirection = Direction of sort. Use "asc" (ascending) or "desc" (descending). [Default is asc]
 	 *
 	 *		[Front-end access control]	
 	 *			checkAccess = Array of access values to filter returned values on. Available for any related table with an "access" field (ca_objects, ca_entities, etc.). If omitted no filtering is performed. [Default is null]
 	 *			user_id = Perform item level access control relative to specified user_id rather than currently logged in user. [Default is user_id for currently logged in user]
 	 *
 	 * @return array List of related items
 	 */
	public function getRelatedItems($pm_rel_table_name_or_num, $pa_options=null, &$pn_count = null) {
		global $AUTH_CURRENT_USER_ID;
		$vn_user_id = (isset($pa_options['user_id']) && $pa_options['user_id']) ? $pa_options['user_id'] : (int)$AUTH_CURRENT_USER_ID;
		$vb_show_if_no_acl = (bool)($this->getAppConfig()->get('default_item_access_level') > __CA_ACL_NO_ACCESS__);

		$va_primary_ids = (isset($pa_options['primaryIDs']) && is_array($pa_options['primaryIDs'])) ? $pa_options['primaryIDs'] : null;
		
		
		$va_get_where = (isset($pa_options['where']) && is_array($pa_options['where']) && sizeof($pa_options['where'])) ? $pa_options['where'] : null;

		$va_row_ids = (isset($pa_options['row_ids']) && is_array($pa_options['row_ids'])) ? $pa_options['row_ids'] : null;
		$vn_row_id = (isset($pa_options['row_id']) && $pa_options['row_id']) ? $pa_options['row_id'] : $this->getPrimaryKey();

		$o_db = $this->getDb();
		$o_tep = new TimeExpressionParser();
		
		$vb_uses_effective_dates = false;

		
		if(isset($pa_options['sort']) && !is_array($pa_options['sort'])) { $pa_options['sort'] = array($pa_options['sort']); }
		$va_sort_fields = (isset($pa_options['sort']) && is_array($pa_options['sort'])) ? $pa_options['sort'] : null;
		$vs_sort_direction = (isset($pa_options['sortDirection']) && $pa_options['sortDirection']) ? $pa_options['sortDirection'] : null;

		if (!$va_row_ids && ($vn_row_id > 0)) {
			$va_row_ids = array($vn_row_id);
		}

		if (!$va_row_ids || !is_array($va_row_ids) || !sizeof($va_row_ids)) { return array(); }

		$vn_limit = (isset($pa_options['limit']) && ((int)$pa_options['limit'] > 0)) ? (int)$pa_options['limit'] : 1000;
		$vn_start = (isset($pa_options['start']) && ((int)$pa_options['start'] > 0)) ? (int)$pa_options['start'] : 0;

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

		switch(sizeof($va_path = array_keys(Datamodel::getPath($this->tableName(), $vs_related_table_name)))) {
			case 3:
				$t_item_rel = Datamodel::getInstanceByTableName($va_path[1]);
				$t_rel_item = Datamodel::getInstanceByTableName($va_path[2]);
				$vs_key = $t_item_rel->primaryKey(); //'relation_id';
				break;
			case 2:
				$t_item_rel = null;
				$t_rel_item = Datamodel::getInstanceByTableName($va_path[1]);
				$vs_key = $t_rel_item->primaryKey();
				break;
			default:
				// bad related table
				return null;
				break;
		}

		$va_wheres = array();
		$va_selects = array();
		$va_joins_post_add = array();

		$vs_related_table = $t_rel_item->tableName();

		if ($t_item_rel) {
			//define table names
			$vs_linking_table = $t_item_rel->tableName();

			$va_selects[] = "{$vs_related_table}.".$t_rel_item->primaryKey();

			if ($t_rel_item->hasField('is_enabled')) {
				$va_selects[] = "{$vs_related_table}.is_enabled";
			}
		}

		
		if (is_array($va_get_where)) {
			foreach($va_get_where as $vs_fld => $vm_val) {
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



		if (isset($pa_options['checkAccess']) && is_array($pa_options['checkAccess']) && sizeof($pa_options['checkAccess']) && $t_rel_item->hasField('access')) {
			$va_wheres[] = "({$vs_related_table}.access IN (".join(',', $pa_options['checkAccess'])."))";
		}

		if ((!isset($pa_options['showDeleted']) || !$pa_options['showDeleted']) && $t_rel_item->hasField('deleted')) {
			$va_wheres[] = "({$vs_related_table}.deleted = 0)";
		}
		
		if (($va_criteria = (isset($pa_options['criteria']) ? $pa_options['criteria'] : null)) && (is_array($va_criteria)) && (sizeof($va_criteria))) {
			$va_wheres[] = "(".join(" AND ", $va_criteria).")"; 
		}

		$va_wheres[] = "(".$this->tableName().'.'.$this->primaryKey()." IN (".join(",", $va_row_ids)."))";
		$va_selects[] = $t_rel_item->tableName().".*";
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

		foreach($va_path as $vs_join_table) {
			$va_rel_info = Datamodel::getRelationships($vs_cur_table, $vs_join_table);
			$va_joins[] = 'INNER JOIN '.$vs_join_table.' ON '.$vs_cur_table.'.'.$va_rel_info[$vs_cur_table][$vs_join_table][0][0].' = '.$vs_join_table.'.'.$va_rel_info[$vs_cur_table][$vs_join_table][0][1]."\n";
			$vs_cur_table = $vs_join_table;
		}

		// If we're getting ca_set_items, we have to rename the intrinsic row_id field because the pk is named row_id below. Hence, this hack.
		if($vs_related_table_name == 'ca_set_items') {
			$va_selects[] = 'ca_set_items.row_id AS record_id';
		}

		$va_selects[] = $this->tableName().'.'.$this->primaryKey().' AS row_id';

		$vs_order_by = '';
		if ($t_item_rel && $t_item_rel->hasField('rank')) {
			$vs_order_by = ' ORDER BY '.$t_item_rel->tableName().'.rank';
		} else {
			if ($t_rel_item && ($vs_sort = $t_rel_item->getProperty('ID_NUMBERING_SORT_FIELD'))) {
				$vs_order_by = " ORDER BY {$vs_related_table}.{$vs_sort}";
			}
		}

		$vs_sql = "
			SELECT DISTINCT ".join(', ', $va_selects)."
			FROM ".$this->tableName()."
			".join("\n", array_merge($va_joins, $va_joins_post_add))."
			WHERE
				".join(' AND ', $va_wheres)."
			{$vs_order_by}
		";

		$qr_res = $o_db->query($vs_sql);
		
		$va_rels = array();
		$vn_c = 0;
		if ($vn_start > 0) { $qr_res->seek($vn_start); }
		while($qr_res->nextRow()) {
			if ($vn_c >= $vn_limit) { break; }
			
			if (is_array($va_primary_ids) && is_array($va_primary_ids[$vs_related_table])) {
				if (in_array($qr_res->get($vs_key), $va_primary_ids[$vs_related_table])) { continue; }
			}
			
			if (isset($pa_options['idsOnly']) && $pa_options['idsOnly']) {
				$va_rels[] = $qr_res->get($t_rel_item->primaryKey());
				continue;
			}

			$va_row = $qr_res->getRow();
			$vs_v = (sizeof($va_path) <= 2) ? $va_row['row_id'].'/'.$va_row[$vs_key] : $va_row[$vs_key];

			$vs_display_label = $va_row[$vs_label_display_field];

			if (!isset($va_rels[$vs_v]) || !$va_rels[$vs_v]) {
				$va_rels[$vs_v] = $va_row;
			}

			$va_rels[$vs_v]['_key'] = $vs_key;
			$va_rels[$vs_v]['direction'] = $vs_direction;

			$vn_c++;
		}			

		//
		// Sort on fields if specified
		//
		if (is_array($va_sort_fields) && sizeof($va_rels)) {
			$va_ids = array();
			$vs_rel_pk = $t_rel_item->primaryKey();
			foreach($va_rels as $vn_i => $va_rel) {
				$va_ids[] = $va_rel[$vs_rel_pk];
			}

			$vs_rel_pk = $t_rel_item->primaryKey();
			foreach($va_sort_fields as $vn_x => $vs_sort_field) {
				if ($vs_sort_field == 'relation_id') { // sort by relationship primary key
					if ($t_item_rel) {
						$va_sort_fields[$vn_x] = $vs_sort_field = $t_item_rel->tableName().'.'.$t_item_rel->primaryKey();
					}
					continue;
				}
				$va_tmp = explode('.', $vs_sort_field);
				if ($va_tmp[0] == $vs_related_table_name) {
					if (!($qr_rel = caMakeSearchResult($va_tmp[0], $va_ids))) { continue; }

					$vs_table = array_shift($va_tmp);
					$vs_key = join(".", $va_tmp);
					while($qr_rel->nextHit()) {
						$vn_pk_val = $qr_rel->get($vs_table.".".$vs_rel_pk);
						foreach($va_rels as $vn_rel_id => $va_rel) {
							if ($va_rel[$vs_rel_pk] == $vn_pk_val) {
								$va_rels[$vn_rel_id][$vs_key] = $qr_rel->get($vs_sort_field, array("delimiter" => ";", 'sortable' => 1));
								break;
							}
						}
					}
				}
			}

			// Perform sort
			$va_rels = caSortArrayByKeyInValue($va_rels, $va_sort_fields, $vs_sort_direction);
		}

		return $va_rels;
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
}
