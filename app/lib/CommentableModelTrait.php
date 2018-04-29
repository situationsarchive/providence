<?php
/** ---------------------------------------------------------------------
 * app/lib/CommentableModelTrait.php :
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
 
trait CommentableModelTrait {
    # --------------------------------------------------------------------------------------------
	# User tagging
	# --------------------------------------------------------------------------------------------
	/**
	 * Adds a tag to currently loaded row. Returns null if no row is loaded. Otherwise returns true
	 * if tag was successfully added, false if an error occurred in which case the errors will be available
	 * via the model's standard error methods (getErrors() and friends.
	 *
	 * Most of the parameters are optional with the exception of $ps_tag - the text of the tag. Note that 
	 * tag text is monolingual; if you want to do multilingual tags then you must add multiple tags.
	 *
	 * The parameters are:
	 *
	 * @param $ps_tag [string] Text of the tag (mandatory)
	 * @param $pn_user_id [integer] A valid ca_users.user_id indicating the user who added the tag; is null for tags from non-logged-in users (optional - default is null)
	 * @param $pn_locale_id [integer] A valid ca_locales.locale_id indicating the language of the comment. If omitted or left null then the value in the global $g_ui_locale_id variable is used. If $g_ui_locale_id is not set and $pn_locale_id is not set then an error will occur (optional - default is to use $g_ui_locale_id)
	 * @param $pn_access [integer] Determines public visibility of tag; if set to 0 then tag is not visible to public; if set to 1 tag is visible (optional - default is 0)
	 * @param $pn_moderator [integer] A valid ca_users.user_id value indicating who moderated the tag; if omitted or set to null then moderation status will not be set unless app.conf setting dont_moderate_comments = 1 (optional - default is null)
	 * @param array $pa_options Array of options. Supported options are:
	 *				purify = if true, comment, name and email are run through HTMLPurifier before being stored in the database. Default is true. 
	 */
	public function addTag($ps_tag, $pn_user_id=null, $pn_locale_id=null, $pn_access=0, $pn_moderator=null, $pa_options=null) {
		global $g_ui_locale_id;
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		if (!$pn_locale_id) { $pn_locale_id = $g_ui_locale_id; }
		if (!$pn_locale_id) { 
			$this->postError(2830, _t('No locale was set for tag'), 'BaseModel->addTag()','ca_item_tags');
			return false;
		}
		
		
		if(!isset($pa_options['purify'])) { $pa_options['purify'] = true; }
		
		if ($this->purify() || (bool)$pa_options['purify']) {
    		$ps_tag = BaseModel::getPurifier()->purify($ps_tag);
		}
		
		$t_tag = new ca_item_tags();
		$t_tag->purify($this->purify() || $pa_options['purify']);
		
		if (!$t_tag->load(array('tag' => $ps_tag, 'locale_id' => $pn_locale_id))) {
			// create new new
			$t_tag->set('tag', $ps_tag);
			$t_tag->set('locale_id', $pn_locale_id);
			$vn_tag_id = $t_tag->insert();
			
			if ($t_tag->numErrors()) {
				$this->errors = $t_tag->errors;
				return false;
			}
		} else {
			$vn_tag_id = $t_tag->getPrimaryKey();
		}
		
		$t_ixt = new ca_items_x_tags();
		$t_ixt->set('table_num', $this->tableNum());
		$t_ixt->set('row_id', $this->getPrimaryKey());
		$t_ixt->set('user_id', $pn_user_id);
		$t_ixt->set('tag_id', $vn_tag_id);
		$t_ixt->set('access', $pn_access);
		
		if (!is_null($pn_moderator)) {
			$t_ixt->set('moderated_by_user_id', $pn_moderator);
			$t_ixt->set('moderated_on', _t('now'));
		}elseif(self::$_CONFIG->get("dont_moderate_comments")){
			$t_ixt->set('moderated_on', _t('now'));
		}
		
		$t_ixt->insert();
		
		if ($t_ixt->numErrors()) {
			$this->errors = $t_ixt->errors;
			return false;
		}
		return true;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Changed the access value for an existing tag. Returns null if no row is loaded. Otherwise returns true
	 * if tag access setting was successfully changed, false if an error occurred in which case the errors will be available
	 * via the model's standard error methods (getErrors() and friends.
	 *
	 * If $pn_user_id is set then only tag relations created by the specified user can be modified. Attempts to modify
	 * tags created by users other than the one specified in $pn_user_id will return false and post an error.
	 *
	 * Most of the parameters are optional with the exception of $ps_tag - the text of the tag. Note that 
	 * tag text is monolingual; if you want to do multilingual tags then you must add multiple tags.
	 *
	 * The parameters are:
	 *
	 * @param $pn_relation_id [integer] A valid ca_items_x_tags.relation_id value specifying the tag relation to modify (mandatory)
	 * @param $pn_access [integer] Determines public visibility of tag; if set to 0 then tag is not visible to public; if set to 1 tag is visible (optional - default is 0)
	 * @param $pn_moderator [integer] A valid ca_users.user_id value indicating who moderated the tag; if omitted or set to null then moderation status will not be set (optional - default is null)
	 * @param $pn_user_id [integer] A valid ca_users.user_id valid; if set only tag relations created by the specified user will be modifed  (optional - default is null)
	 */
	public function changeTagAccess($pn_relation_id, $pn_access=0, $pn_moderator=null, $pn_user_id=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		
		$t_ixt = new ca_items_x_tags($pn_relation_id);
		
		if (!$t_ixt->getPrimaryKey()) {
			$this->postError(2800, _t('Tag relation id is invalid'), 'BaseModel->changeTagAccess()', 'ca_item_tags');
			return false;
		}
		if (
			($t_ixt->get('table_num') != $this->tableNum()) ||
			($t_ixt->get('row_id') != $vn_row_id)
		) {
			$this->postError(2810, _t('Tag is not part of the current row'), 'BaseModel->changeTagAccess()', 'ca_item_tags');
			return false;
		}
		
		if ($pn_user_id) {
			if ($t_ixt->get('user_id') != $pn_user_id) {
				$this->postError(2820, _t('Tag was not created by specified user'), 'BaseModel->changeTagAccess()', 'ca_item_tags');
				return false;
			}
		}
		
		$t_ixt->set('access', $pn_access);
		
		if (!is_null($pn_moderator)) {
			$t_ixt->set('moderated_by_user_id', $pn_moderator);
			$t_ixt->set('moderated_on', 'now');
		}
		
		$t_ixt->update();
		
		if ($t_ixt->numErrors()) {
			$this->errors = $t_ixt->errors;
			return false;
		}
		return true;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Deletes the tag relation specified by $pn_relation_id (a ca_items_x_tags.relation_id value) from the currently loaded row. Will only delete 
	 * tags attached to the currently loaded row. If you attempt to delete a ca_items_x_tags.relation_id not attached to the current row 
	 * removeTag() will return false and post an error. If you attempt to call removeTag() with no row loaded null will be returned.
	 * If $pn_user_id is specified then only tags created by the specified user will be deleted; if the tag being
	 * deleted is not created by the user then false is returned and an error posted.
	 *
	 * @param $pn_relation_id [integer] a valid ca_items_x_tags.relation_id to be removed; must be related to the currently loaded row (mandatory)
	 * @param $pn_user_id [integer] a valid ca_users.user_id value; if specified then only tag relations added by the specified user will be deleted (optional - default is null)
	 */
	public function removeTag($pn_relation_id, $pn_user_id=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		
		$t_ixt = new ca_items_x_tags($pn_relation_id);
		
		if (!$t_ixt->getPrimaryKey()) {
			$this->postError(2800, _t('Tag relation id is invalid'), 'BaseModel->removeTag()', 'ca_item_tags');
			return false;
		}
		if (
			($t_ixt->get('table_num') != $this->tableNum()) ||
			($t_ixt->get('row_id') != $vn_row_id)
		) {
			$this->postError(2810, _t('Tag is not part of the current row'), 'BaseModel->removeTag()', 'ca_item_tags');
			return false;
		}
		
		if ($pn_user_id) {
			if ($t_ixt->get('user_id') != $pn_user_id) {
				$this->postError(2820, _t('Tag was not created by specified user'), 'BaseModel->removeTag()', 'ca_item_tags');
				return false;
			}
		}
		
		$t_ixt->delete();
		
		if ($t_ixt->numErrors()) {
			$this->errors = $t_ixt->errors;
			return false;
		}
		return true;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Removes all tags associated with the currently loaded row. Will return null if no row is currently loaded.
	 * If the optional $ps_user_id parameter is passed then only tags added by the specified user will be removed.
	 *
	 * @param $pn_user_id [integer] A valid ca_users.user_id value. If specified, only tags added by the specified user will be removed. (optional - default is null)
	 */
	public function removeAllTags($pn_user_id=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		
		$va_tags = $this->getTags($pn_user_id);
		
		foreach($va_tags as $va_tag) {
			if (!$this->removeTag($va_tag['tag_id'], $pn_user_id)) {
				return false;
			}
		}
		return true;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Returns all tags associated with the currently loaded row. Will return null if not row is currently loaded.
	 * If the optional $ps_user_id parameter is passed then only tags created by the specified user will be returned.
	 * If the optional $pb_moderation_status parameter is passed then only tags matching the criteria will be returned:
	 *		Passing $pb_moderation_status = TRUE will cause only moderated tags to be returned
	 *		Passing $pb_moderation_status = FALSE will cause only unmoderated tags to be returned
	 *		If you want both moderated and unmoderated tags to be returned then omit the parameter or pass a null value
	 *
	 * @param $pn_user_id [integer] A valid ca_users.user_id value. If specified, only tags added by the specified user will be returned. (optional - default is null)
	 * @param $pb_moderation_status [boolean] To return only unmoderated tags set to FALSE; to return only moderated tags set to TRUE; to return all tags set to null or omit
	 */
	public function getTags($pn_user_id=null, $pb_moderation_status=null, $pn_row_id=null) {
		if (!($vn_row_id = $pn_row_id)) {
			if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		}
		$o_db = $this->getDb();
		
		$vs_user_sql = ($pn_user_id) ? ' AND (cixt.user_id = '.intval($pn_user_id).')' : '';
		
		$vs_moderation_sql = '';
		if (!is_null($pb_moderation_status)) {
			$vs_moderation_sql = ($pb_moderation_status) ? ' AND (cixt.moderated_on IS NOT NULL)' : ' AND (cixt.moderated_on IS NULL)';
		}
		
		$qr_comments = $o_db->query("
			SELECT *
			FROM ca_item_tags cit
			INNER JOIN ca_items_x_tags AS cixt ON cit.tag_id = cixt.tag_id
			WHERE
				(cixt.table_num = ?) AND (cixt.row_id = ?) {$vs_user_sql} {$vs_moderation_sql}
		", $this->tableNum(), $vn_row_id);
		
		return $qr_comments->getAllRows();
	}
	# --------------------------------------------------------------------------------------------
	# User commenting
	# --------------------------------------------------------------------------------------------
	/**
	 * Adds a comment to currently loaded row. Returns null if no row is loaded. Otherwise returns true
	 * if comment was successfully added, false if an error occurred in which case the errors will be available
	 * via the model's standard error methods (getErrors() and friends.
	 *
	 * Most of the parameters are optional with the exception of $ps_comment - the text of the comment. Note that 
	 * comment text is monolingual; if you want to do multilingual comments (which aren't really comments then, are they?) then
	 * you should add multiple comments.
	 *
	 * @param $ps_comment [string] Text of the comment (mandatory)
	 * @param $pn_rating [integer] A number between 1 and 5 indicating the user's rating of the row; larger is better (optional - default is null)
	 * @param $pn_user_id [integer] A valid ca_users.user_id indicating the user who posted the comment; is null for comments from non-logged-in users (optional - default is null)
	 * @param $pn_locale_id [integer] A valid ca_locales.locale_id indicating the language of the comment. If omitted or left null then the value in the global $g_ui_locale_id variable is used. If $g_ui_locale_id is not set and $pn_locale_id is not set then an error will occur (optional - default is to use $g_ui_locale_id)
	 * @param $ps_name [string] Name of user posting comment. Only needs to be set if $pn_user_id is *not* set; used to identify comments posted by non-logged-in users (optional - default is null)
	 * @param $ps_email [string] E-mail address of user posting comment. Only needs to be set if $pn_user_id is *not* set; used to identify comments posted by non-logged-in users (optional - default is null)
	 * @param $pn_access [integer] Determines public visibility of comments; if set to 0 then comment is not visible to public; if set to 1 comment is visible (optional - default is 0)
	 * @param $pn_moderator [integer] A valid ca_users.user_id value indicating who moderated the comment; if omitted or set to null then moderation status will not be set unless app.conf setting dont_moderate_comments = 1 (optional - default is null)
	 * @param array $pa_options Array of options. Supported options are:
	 *				purify = if true, comment, name and email are run through HTMLPurifier before being stored in the database. Default is true.
	 *				media1_original_filename = original file name to set for comment "media1"
	 *				media2_original_filename = original file name to set for comment "media2"
	 *				media3_original_filename = original file name to set for comment "media3"
	 *				media4_original_filename = original file name to set for comment "media4"
	 * @param $ps_location [string] = location of user
	 * @return ca_item_comments BaseModel representation of newly created comment, false on error or null if parameters are invalid
	 */
	public function addComment($ps_comment, $pn_rating=null, $pn_user_id=null, $pn_locale_id=null, $ps_name=null, $ps_email=null, $pn_access=0, $pn_moderator=null, $pa_options=null, $ps_media1=null, $ps_media2=null, $ps_media3=null, $ps_media4=null, $ps_location=null) {
		global $g_ui_locale_id;
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		if (!$pn_locale_id) { $pn_locale_id = $g_ui_locale_id; }
		
		if(!isset($pa_options['purify'])) { $pa_options['purify'] = true; }
		
		if ((bool)$pa_options['purify']) {
    		$ps_comment = BaseModel::getPurifier()->purify($ps_comment);
    		$ps_name = BaseModel::getPurifier()->purify($ps_name);
    		$ps_email = BaseModel::getPurifier()->purify($ps_email);
		}
		
		$t_comment = new ca_item_comments();
		$t_comment->purify($this->purify() || $pa_options['purify']);
		$t_comment->set('table_num', $this->tableNum());
		$t_comment->set('row_id', $vn_row_id);
		$t_comment->set('user_id', $pn_user_id);
		$t_comment->set('locale_id', $pn_locale_id);
		$t_comment->set('comment', $ps_comment);
		$t_comment->set('rating', $pn_rating);
		$t_comment->set('email', $ps_email);
		$t_comment->set('name', $ps_name);
		$t_comment->set('access', $pn_access);
		$t_comment->set('media1', $ps_media1, array('original_filename' => $pa_options['media1_original_filename']));
		$t_comment->set('media2', $ps_media2, array('original_filename' => $pa_options['media2_original_filename']));
		$t_comment->set('media3', $ps_media3, array('original_filename' => $pa_options['media3_original_filename']));
		$t_comment->set('media4', $ps_media4, array('original_filename' => $pa_options['media4_original_filename']));
		$t_comment->set('location', $ps_location);
		
		if (!is_null($pn_moderator)) {
			$t_comment->set('moderated_by_user_id', $pn_moderator);
			$t_comment->set('moderated_on', 'now');
		}elseif(self::$_CONFIG->get("dont_moderate_comments")){
			$t_comment->set('moderated_on', 'now');
		}
		
		$t_comment->insert();
		
		if ($t_comment->numErrors()) {
			$this->errors = $t_comment->errors;
			return false;
		}
		
		return $t_comment;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Edits an existing comment as specified by $pn_comment_id. Will only edit comments that are attached to the 
	 * currently loaded row. If called with no row loaded editComment() will return null. If you attempt to modify
	 * a comment not associated with the currently loaded row editComment() will return false and post an error.
	 * Note that all parameters are mandatory in the sense that the value passed (or the default value if not passed)
	 * will be written into the comment. For example, if you don't bother passing $ps_name then it will be set to null, even
	 * if there's an existing name value in the field. The only exception is $pn_locale_id; if set to null or omitted then 
	 * editComment() will attempt to use the locale value in the global $g_ui_locale_id variable. If this is not set then
	 * an error will be posted and editComment() will return false.
	 *
	 * @param $pn_comment_id [integer] a valid comment_id to be edited; must be related to the currently loaded row (mandatory)
	 * @param $ps_comment [string] the text of the comment (mandatory)
	 * @param $pn_rating [integer] a number between 1 and 5 indicating the user's rating of the row; higher is better (optional - default is null)
	 * @param $pn_user_id [integer] A valid ca_users.user_id indicating the user who posted the comment; is null for comments from non-logged-in users (optional - default is null)
	 * @param $pn_locale_id [integer] A valid ca_locales.locale_id indicating the language of the comment. If omitted or left null then the value in the global $g_ui_locale_id variable is used. If $g_ui_locale_id is not set and $pn_locale_id is not set then an error will occur (optional - default is to use $g_ui_locale_id)
	 * @param $ps_name [string] Name of user posting comment. Only needs to be set if $pn_user_id is *not* set; used to identify comments posted by non-logged-in users (optional - default is null)
	 * @param $ps_email [string] E-mail address of user posting comment. Only needs to be set if $pn_user_id is *not* set; used to identify comments posted by non-logged-in users (optional - default is null)
	 * @param $pn_access [integer] Determines public visibility of comments; if set to 0 then comment is not visible to public; if set to 1 comment is visible (optional - default is 0)
	 * @param $pn_moderator [integer] A valid ca_users.user_id value indicating who moderated the comment; if omitted or set to null then moderation status will not be set (optional - default is null)
	 * @param array $pa_options Array of options. Supported options are:
	 *				purify = if true, comment, name and email are run through HTMLPurifier before being stored in the database. Default is true. 
	 *				media1_original_filename = original file name to set for comment "media1"
	 *				media2_original_filename = original file name to set for comment "media2"
	 *				media3_original_filename = original file name to set for comment "media3"
	 *				media4_original_filename = original file name to set for comment "media4"
	 */
	public function editComment($pn_comment_id, $ps_comment, $pn_rating=null, $pn_user_id=null, $pn_locale_id=null, $ps_name=null, $ps_email=null, $pn_access=null, $pn_moderator=null, $pa_options=null,  $ps_media1=null, $ps_media2=null, $ps_media3=null, $ps_media4=null) {
		global $g_ui_locale_id;
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		if (!$pn_locale_id) { $pn_locale_id = $g_ui_locale_id; }
		
		$t_comment = new ca_item_comments($pn_comment_id);
		if (!$t_comment->getPrimaryKey()) {
			$this->postError(2800, _t('Comment id is invalid'), 'BaseModel->editComment()', 'ca_item_comments');
			return false;
		}
		if (
			($t_comment->get('table_num') != $this->tableNum()) ||
			($t_comment->get('row_id') != $vn_row_id)
		) {
			$this->postError(2810, _t('Comment is not part of the current row'), 'BaseModel->editComment()', 'ca_item_comments');
			return false;
		}
		
		
		if(!isset($pa_options['purify'])) { $pa_options['purify'] = true; }
		$t_comment->purify($this->purify() || $pa_options['purify']);
		
		if ((bool)$pa_options['purify']) {
    		$ps_comment = BaseModel::getPurifier()->purify($ps_comment);
    		$ps_name = BaseModel::getPurifier()->purify($ps_name);
    		$ps_email = BaseModel::getPurifier()->purify($ps_email);
		}
		
		$t_comment->set('comment', $ps_comment);
		$t_comment->set('rating', $pn_rating);
		$t_comment->set('user_id', $pn_user_id);
		$t_comment->set('name', $ps_name);
		$t_comment->set('email', $ps_email);
		$t_comment->set('media1', $ps_media1, array('original_filename' => $pa_options['media1_original_filename']));
		$t_comment->set('media2', $ps_media2, array('original_filename' => $pa_options['media2_original_filename']));
		$t_comment->set('media3', $ps_media3, array('original_filename' => $pa_options['media3_original_filename']));
		$t_comment->set('media4', $ps_media4, array('original_filename' => $pa_options['media4_original_filename']));
		
		if (!is_null($pn_moderator)) {
			$t_comment->set('moderated_by_user_id', $pn_moderator);
			$t_comment->set('moderated_on', 'now');
		}
		
		if (!is_null($pn_locale_id)) { $t_comment->set('locale_id', $pn_locale_id); }
		
		$t_comment->update();
		if ($t_comment->numErrors()) {
			$this->errors = $t_comment->errors;
			return false;
		}
		return true;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Permanently deletes the comment specified by $pn_comment_id. Will only delete comments attached to the
	 * currently loaded row. If you attempt to delete a comment_id not attached to the current row removeComment()
	 * will return false and post an error. If you attempt to call removeComment() with no row loaded null will be returned.
	 * If $pn_user_id is specified then only comments created by the specified user will be deleted; if the comment being
	 * deleted is not created by the user then false is returned and an error posted.
	 *
	 * @param $pn_comment_id [integer] a valid comment_id to be removed; must be related to the currently loaded row (mandatory)
	 * @param $pn_user_id [integer] a valid ca_users.user_id value; if specified then only comments by the specified user will be deleted (optional - default is null)
	 */
	public function removeComment($pn_comment_id, $pn_user_id=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		
		$t_comment = new ca_item_comments($pn_comment_id);
		if (!$t_comment->getPrimaryKey()) {
			$this->postError(2800, _t('Comment id is invalid'), 'BaseModel->removeComment()', 'ca_item_comments');
			return false;
		}
		if (
			($t_comment->get('table_num') != $this->tableNum()) ||
			($t_comment->get('row_id') != $vn_row_id)
		) {
			$this->postError(2810, _t('Comment is not part of the current row'), 'BaseModel->removeComment()', 'ca_item_comments');
			return false;
		}
		
		if ($pn_user_id) {
			if ($t_comment->get('user_id') != $pn_user_id) {
				$this->postError(2820, _t('Comment was not created by specified user'), 'BaseModel->removeComment()', 'ca_item_comments');
				return false;
			}
		}
		
		$t_comment->delete();
		
		if ($t_comment->numErrors()) {
			$this->errors = $t_comment->errors;
			return false;
		}
		return true;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Removes all comments associated with the currently loaded row. Will return null if no row is currently loaded.
	 * If the optional $ps_user_id parameter is passed then only comments created by the specified user will be removed.
	 *
	 * @param $pn_user_id [integer] A valid ca_users.user_id value. If specified, only comments by the specified user will be removed. (optional - default is null)
	 */
	public function removeAllComments($pn_user_id=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		
		$va_comments = $this->getComments($pn_user_id);
		
		foreach($va_comments as $va_comment) {
			if (!$this->removeComment($va_comment['comment_id'], $pn_user_id)) {
				return false;
			}
		}
		return true;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Returns all comments associated with the currently loaded row. Will return null if not row is currently loaded.
	 * If the optional $ps_user_id parameter is passed then only comments created by the specified user will be returned.
	 * If the optional $pb_moderation_status parameter is passed then only comments matching the criteria will be returned:
	 *		Passing $pb_moderation_status = TRUE will cause only moderated comments to be returned
	 *		Passing $pb_moderation_status = FALSE will cause only unmoderated comments to be returned
	 *		If you want both moderated and unmoderated comments to be returned then omit the parameter or pass a null value
	 *
	 * @param int $pn_user_id A valid ca_users.user_id value. If specified, only comments by the specified user will be returned. (optional - default is null)
	 * @param bool $pn_moderation_status  To return only unmoderated comments set to FALSE; to return only moderated comments set to TRUE; to return all comments set to null or omit
	 * @param array $pa_options Options include:
     * 	    transaction = optional Transaction instance. If set then all database access is done within the context of the transaction
     *		returnAs = what to return; possible values are:
     *          array                   = an array of comments
     *			searchResult			= a search result instance (aka. a subclass of BaseSearchResult), when the calling subclass is searchable (ie. <classname>Search and <classname>SearchResult classes are defined)
     *			ids						= an array of ids (aka. primary keys)
     *			modelInstances			= an array of instances, one for each match. Each instance is the same class as the caller, a subclass of BaseModel
     *			firstId					= the id (primary key) of the first match. This is the same as the first item in the array returned by 'ids'
     *			firstModelInstance		= the instance of the first match. This is the same as the first instance in the array returned by 'modelInstances'
     *			count					= the number of matches
     *
     *			The default is array
     *
     * @return array
     */
	public function getComments($pn_user_id=null, $pb_moderation_status=null, $pa_options=null) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }

        $o_trans = caGetOption('transaction', $pa_options, null);
        $vs_return_as = caGetOption('returnAs', $pa_options, 'array');

		$o_db = $o_trans ? $o_trans->getDb() : $this->getDb();
		
		$vs_user_sql = ($pn_user_id) ? ' AND (user_id = '.intval($pn_user_id).')' : '';
		
		$vs_moderation_sql = '';
		if (!is_null($pb_moderation_status)) {
			$vs_moderation_sql = ($pb_moderation_status) ? ' AND (ca_item_comments.moderated_on IS NOT NULL)' : ' AND (ca_item_comments.moderated_on IS NULL)';
		}
		
		$qr_comments = $o_db->query("
			SELECT *
			FROM ca_item_comments
			WHERE
				(table_num = ?) AND (row_id = ?) {$vs_user_sql} {$vs_moderation_sql}
		", array($this->tableNum(), $vn_row_id));

        switch($vs_return_as) {
            case 'count':
                return $qr_comments->numRows();
                break;
            case 'ids':
            case 'firstId':
            case 'searchResult':
            case 'modelInstances':
            case 'firstModelInstance':
                $va_ids = $qr_comments->getAllFieldValues('comment_id');
                if ($vs_return_as === 'ids') { return $va_ids; }
                if ($vs_return_as === 'firstId') { return array_shift($va_ids); }
                if (($vs_return_as === 'modelInstances') || ($vs_return_as === 'firstModelInstance')) {
                    $va_acc = array();
                    foreach($va_ids as $vn_id) {
                        $t_instance = new ca_item_comments($vn_id);
                        if ($vs_return_as === 'firstModelInstance') { return $t_instance; }
                        $va_acc[] = $t_instance;
                    }
                    return $va_acc;
                }
                return caMakeSearchResult('ca_item_comments', $va_ids);
                break;
            case 'array':
            default:
                $va_comments = array();
                while ($qr_comments->nextRow()) {
                    $va_comments[$qr_comments->get("comment_id")] = $qr_comments->getRow();
                    foreach (array("media1", "media2", "media3", "media4") as $vs_media_field) {
                        $va_media_versions = array();
                        $va_media_versions = $qr_comments->getMediaVersions($vs_media_field);
                        $va_media = array();
                        if (is_array($va_media_versions) && (sizeof($va_media_versions) > 0)) {
                            foreach ($va_media_versions as $vs_version) {
                                $va_image_info = array();
                                $va_image_info = $qr_comments->getMediaInfo($vs_media_field, $vs_version);
                                $va_image_info["TAG"] = $qr_comments->getMediaTag($vs_media_field, $vs_version);
                                $va_image_info["URL"] = $qr_comments->getMediaUrl($vs_media_field, $vs_version);
                                $va_media[$vs_version] = $va_image_info;
                            }
                            $va_comments[$qr_comments->get("comment_id")][$vs_media_field] = $va_media;
                        }
                    }
                }
                break;
        }
		return $va_comments;
	}
	# --------------------------------------------------------------------------------------------
	/** 
	 * Returns average user rating of item
	 */ 
	public function getAverageRating($pb_moderation_status=true) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
	
		$vs_moderation_sql = '';
		if (!is_null($pb_moderation_status)) {
			$vs_moderation_sql = ($pb_moderation_status) ? ' AND (ca_item_comments.moderated_on IS NOT NULL)' : ' AND (ca_item_comments.moderated_on IS NULL)';
		}
		
		$o_db = $this->getDb();
		$qr_comments = $o_db->query("
			SELECT avg(rating) r
			FROM ca_item_comments
			WHERE
				(table_num = ?) AND (row_id = ?) {$vs_moderation_sql}
		", $this->tableNum(), $vn_row_id);
		
		if ($qr_comments->nextRow()) {
			return round($qr_comments->get('r'));
		} else {
			return null;
		}
	}
	# --------------------------------------------------------------------------------------------
	/** 
	 * Returns number of user comments for item
	 */ 
	public function getNumComments($pb_moderation_status=true) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
	
		$vs_moderation_sql = '';
		if (!is_null($pb_moderation_status)) {
			$vs_moderation_sql = ($pb_moderation_status) ? ' AND (ca_item_comments.moderated_on IS NOT NULL)' : ' AND (ca_item_comments.moderated_on IS NULL)';
		}
		
		$o_db = $this->getDb();
		$qr_comments = $o_db->query("
			SELECT count(*) c
			FROM ca_item_comments
			WHERE
				(comment != '') AND (table_num = ?) AND (row_id = ?) {$vs_moderation_sql}
		", $this->tableNum(), $vn_row_id);
		
		if ($qr_comments->nextRow()) {
			return round($qr_comments->get('c'));
		} else {
			return null;
		}
	}
	# --------------------------------------------------------------------------------------------
	/** 
	 * Returns number of user comments for items with ids
	 */ 
	static public function getNumCommentsForIDs($pa_ids, $pb_moderation_status=true, $pa_options=null) {
		if(!is_array($pa_ids) || !sizeof($pa_ids)) { return null; }

		$vs_moderation_sql = '';
		if (!is_null($pb_moderation_status)) {
			$vs_moderation_sql = ($pb_moderation_status) ? ' AND (ca_item_comments.moderated_on IS NOT NULL)' : ' AND (ca_item_comments.moderated_on IS NULL)';
		}
		
				if (!($vn_table_num = Datamodel::getTableNum(get_called_class()))) { return null; }
		
		$o_db = ($o_trans = caGetOption('transaction', $pa_options, null)) ? $o_trans->getDb() : new Db();
		$qr_comments = $o_db->query("
			SELECT row_id, count(*) c
			FROM ca_item_comments
			WHERE
				(comment != '') AND (table_num = ?) AND (row_id IN (?)) {$vs_moderation_sql}
			GROUP BY row_id
		", array($vn_table_num, $pa_ids));
		
		$va_counts = array();
		while ($qr_comments->nextRow()) {
			$va_counts[(int)$qr_comments->get('row_id')] = (int)$qr_comments->get('c');
		}
		return $va_counts;
	}
	# --------------------------------------------------------------------------------------------
	/** 
	 * Returns number of user ratings for item
	 */ 
	public function getNumRatings($pb_moderation_status=true) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
	
		$vs_moderation_sql = '';
		if (!is_null($pb_moderation_status)) {
			$vs_moderation_sql = ($pb_moderation_status) ? ' AND (ca_item_comments.moderated_on IS NOT NULL)' : ' AND (ca_item_comments.moderated_on IS NULL)';
		}
		
		$o_db = $this->getDb();
		$qr_ratings = $o_db->query("
			SELECT count(*) c
			FROM ca_item_comments
			WHERE
				(rating > 0) AND (table_num = ?) AND (row_id = ?) {$vs_moderation_sql}
		", $this->tableNum(), $vn_row_id);
		
		if ($qr_ratings->nextRow()) {
			return round($qr_ratings->get('c'));
		} else {
			return null;
		}
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Return the highest rated item(s)
	 * Return an array of primary key values
	 */
	public function getHighestRated($pb_moderation_status=true, $pn_num_to_return=1, $va_access_values = array()) {
		$vs_moderation_sql = '';
		if (!is_null($pb_moderation_status)) {
			$vs_moderation_sql = ($pb_moderation_status) ? ' AND (ca_item_comments.moderated_on IS NOT NULL)' : ' AND (ca_item_comments.moderated_on IS NULL)';
		}
		$vs_access_join = "";
		$vs_access_where = "";	
		$vs_table_name = $this->tableName();
		$vs_primary_key = $this->primaryKey();
		if (isset($va_access_values) && is_array($va_access_values) && sizeof($va_access_values) && $this->hasField('access')) {	
			if ($vs_table_name && $vs_primary_key) {
				$vs_access_join = 'INNER JOIN '.$vs_table_name.' as rel ON rel.'.$vs_primary_key." = ca_item_comments.row_id ";
				$vs_access_where = ' AND rel.access IN ('.join(',', $va_access_values).')';
			}
		}
		
		
		$vs_deleted_sql = '';
		if ($this->hasField('deleted')) {
			$vs_deleted_sql = " AND (rel.deleted = 0) ";
		}
		
		if ($vs_deleted_sql || $vs_access_where) {
			$vs_access_join = 'INNER JOIN '.$vs_table_name.' as rel ON rel.'.$vs_primary_key." = ca_item_comments.row_id ";
		}
	
		$o_db = $this->getDb();
		$qr_comments = $o_db->query($x="
			SELECT ca_item_comments.row_id
			FROM ca_item_comments
			{$vs_access_join}
			WHERE
				(ca_item_comments.table_num = ?)
				{$vs_moderation_sql}
				{$vs_access_where}
				{$vs_deleted_sql}
			GROUP BY
				ca_item_comments.row_id
			ORDER BY
				avg(ca_item_comments.rating) DESC, MAX(ca_item_comments.created_on) DESC
			LIMIT {$pn_num_to_return}
		", $this->tableNum());
	
		$va_row_ids = array();
		while ($qr_comments->nextRow()) {
			$va_row_ids[] = $qr_comments->get('row_id');
		}
		return $va_row_ids;
	}
	# --------------------------------------------------------------------------------------------
	/**
	 * Return the number of ratings
	 * Return an integer count
	 */
	public function getRatingsCount($pb_moderation_status=true) {
		if (!($vn_row_id = $this->getPrimaryKey())) { return null; }
		$vs_moderation_sql = '';
		if (!is_null($pb_moderation_status)) {
			$vs_moderation_sql = ($pb_moderation_status) ? ' AND (ca_item_comments.moderated_on IS NOT NULL)' : ' AND (ca_item_comments.moderated_on IS NULL)';
		}
		
		$o_db = $this->getDb();
		$qr_comments = $o_db->query("
			SELECT count(*) c
			FROM ca_item_comments
			WHERE
				(ca_item_comments.table_num = ?) AND (ca_item_comments.row_id = ?)
				{$vs_moderation_sql}
		", $this->tableNum(), $vn_row_id);
		
		if ($qr_comments->nextRow()) {
			return $qr_comments->get('c');
		}
		return 0;
	}
}
