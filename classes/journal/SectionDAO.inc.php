<?php

/**
 * @file classes/journal/SectionDAO.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SectionDAO
 * @ingroup journal
 * @see Section
 *
 * @brief Operations for retrieving and modifying Section objects.
 */

import ('classes.journal.Section');

class SectionDAO extends DAO {
	var $cache;

	function _cacheMiss(&$cache, $id) {
		$section = $this->getById($id, null, false);
		$cache->setCache($id, $section);
		return $section;
	}

	function &_getCache() {
		if (!isset($this->cache)) {
			$cacheManager = CacheManager::getManager();
			$this->cache = $cacheManager->getObjectCache('sections', 0, array($this, '_cacheMiss'));
		}
		return $this->cache;
	}

	/**
	 * Retrieve a section by ID.
	 * @param $sectionId int
	 * @param $journalId int optional
	 * @param $useCache boolean optional
	 * @return Section
	 */
	function getById($sectionId, $journalId = null, $useCache = false) {
		if ($useCache) {
			$cache = $this->_getCache();
			$returner = $cache->get($sectionId);
			if ($returner && $journalId != null && $journalId != $returner->getJournalId()) $returner = null;
			return $returner;
		}

		$sql = 'SELECT * FROM sections WHERE section_id = ?';
		$params = array((int) $sectionId);
		if ($journalId !== null) {
			$sql .= ' AND journal_id = ?';
			$params[] = (int) $journalId;
		}
		$result = $this->retrieve($sql, $params);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_returnSectionFromRow($result->GetRowAssoc(false));
		}
		$result->Close();

		return $returner;
	}

	/**
	 * Retrieve a section by abbreviation.
	 * @param $sectionAbbrev string
	 * @param $locale string optional
	 * @return Section
	 */
	function getByAbbrev($sectionAbbrev, $journalId, $locale = null) {
		$params = array('abbrev', $sectionAbbrev, (int) $journalId);
		if ($locale !== null) {
			$params[] = $locale;
		}

		$result = $this->retrieve(
			'SELECT	s.*
			FROM	sections s, section_settings l
			WHERE	l.section_id = s.section_id AND
				l.setting_name = ? AND
				l.setting_value = ? AND
				s.journal_id = ?' .
				($locale!==null?' AND l.locale = ?':''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_returnSectionFromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a section by title.
	 * @param $sectionTitle string
	 * @return Section
	 */
	function getByTitle($sectionTitle, $journalId, $locale = null) {
		$params = array('title', $sectionTitle, (int) $journalId);
		if ($locale !== null) {
			$params[] = $locale;
		}

		$result = $this->retrieve(
			'SELECT	s.*
			FROM	sections s, section_settings l
			WHERE	l.section_id = s.section_id AND
				l.setting_name = ? AND
				l.setting_value = ? AND
				s.journal_id = ?' .
				($locale !== null?' AND l.locale = ?':''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_returnSectionFromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Return a new data object.
	 */
	function newDataObject() {
		return new Section();
	}

	/**
	 * Internal function to return a Section object from a row.
	 * @param $row array
	 * @return Section
	 */
	function _returnSectionFromRow($row) {
		$section = $this->newDataObject();
		$section->setId($row['section_id']);
		$section->setJournalId($row['journal_id']);
		$section->setReviewFormId($row['review_form_id']);
		$section->setSequence($row['seq']);
		$section->setMetaIndexed($row['meta_indexed']);
		$section->setMetaReviewed($row['meta_reviewed']);
		$section->setAbstractsNotRequired($row['abstracts_not_required']);
		$section->setEditorRestricted($row['editor_restricted']);
		$section->setHideTitle($row['hide_title']);
		$section->setHideAuthor($row['hide_author']);
		$section->setHideAbout($row['hide_about']);
		$section->setDisableComments($row['disable_comments']);
		$section->setAbstractWordCount($row['abstract_word_count']);

		$this->getDataObjectSettings('section_settings', 'section_id', $row['section_id'], $section);

		HookRegistry::call('SectionDAO::_returnSectionFromRow', array(&$section, &$row));

		return $section;
	}

	/**
	 * Get the list of fields for which data can be localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title', 'abbrev', 'policy', 'identifyType');
	}

	/**
	 * Update the localized fields for this table
	 * @param $section object
	 */
	function updateLocaleFields($section) {
		$this->updateDataObjectSettings('section_settings', $section, array(
			'section_id' => $section->getId()
		));
	}

	/**
	 * Insert a new section.
	 * @param $section Section
	 */
	function insertObject($section) {
		$this->update(
			'INSERT INTO sections
				(journal_id, review_form_id, seq, meta_indexed, meta_reviewed, abstracts_not_required, editor_restricted, hide_title, hide_author, hide_about, disable_comments, abstract_word_count)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			array(
				(int)$section->getJournalId(),
				(int)$section->getReviewFormId(),
				$section->getSequence() == null ? 0 : $section->getSequence(),
				$section->getMetaIndexed() ? 1 : 0,
				$section->getMetaReviewed() ? 1 : 0,
				$section->getAbstractsNotRequired() ? 1 : 0,
				$section->getEditorRestricted() ? 1 : 0,
				$section->getHideTitle() ? 1 : 0,
				$section->getHideAuthor() ? 1 : 0,
				$section->getHideAbout() ? 1 : 0,
				$section->getDisableComments() ? 1 : 0,
				(int) $section->getAbstractWordCount()
			)
		);

		$section->setId($this->getInsertId());
		$this->updateLocaleFields($section);
		return $section->getId();
	}

	/**
	 * Update an existing section.
	 * @param $section Section
	 */
	function updateObject($section) {
		$this->update(
			'UPDATE sections
				SET
					review_form_id = ?,
					seq = ?,
					meta_indexed = ?,
					meta_reviewed = ?,
					abstracts_not_required = ?,
					editor_restricted = ?,
					hide_title = ?,
					hide_author = ?,
					hide_about = ?,
					disable_comments = ?,
					abstract_word_count = ?
				WHERE section_id = ?',
			array(
				(int)$section->getReviewFormId(),
				$this->nullOrInt($section->getSequence()),
				(int)$section->getMetaIndexed(),
				(int)$section->getMetaReviewed(),
				(int)$section->getAbstractsNotRequired(),
				(int)$section->getEditorRestricted(),
				(int)$section->getHideTitle(),
				(int)$section->getHideAuthor(),
				(int)$section->getHideAbout(),
				(int)$section->getDisableComments(),
				$this->nullOrInt($section->getAbstractWordCount()),
				(int)$section->getId()
			)
		);
		$this->updateLocaleFields($section);
	}

	/**
	 * Delete a section.
	 * @param $section Section
	 */
	function deleteObject($section) {
		return $this->deleteById($section->getId(), $section->getJournalId());
	}

	/**
	 * Delete a section by ID.
	 * @param $sectionId int
	 * @param $journalId int optional
	 */
	function deleteById($sectionId, $journalId = null) {
		$sectionEditorsDao = DAORegistry::getDAO('SectionEditorsDAO');
		$sectionEditorsDao->deleteEditorsBySectionId($sectionId, $journalId);

		// Remove articles from this section
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$articleDao->removeArticlesFromSection($sectionId);

		// Delete published article entries from this section -- they must
		// be re-published.
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$publishedArticleDao->deletePublishedArticlesBySectionId($sectionId);

		if (isset($journalId) && !$this->sectionExists($sectionId, $journalId)) return false;
		$this->update('DELETE FROM section_settings WHERE section_id = ?', (int) $sectionId);
		$this->update('DELETE FROM sections WHERE section_id = ?', (int) $sectionId);
	}

	/**
	 * Delete sections by journal ID
	 * NOTE: This does not delete dependent entries EXCEPT from section_editors. It is intended
	 * to be called only when deleting a journal.
	 * @param $journalId int
	 */
	function deleteByJournalId($journalId) {
		$sections = $this->getByJournalId($journalId);
		while ($section = $sections->next()) {
			$this->deleteObject($section);
		}
	}

	/**
	 * Retrieve an array associating all section editor IDs with
	 * arrays containing the sections they edit.
	 * @return array editorId => array(sections they edit)
	 */
	function &getEditorSections($journalId) {
		$returner = array();

		$result = $this->retrieve(
			'SELECT s.*, se.user_id AS editor_id FROM section_editors se, sections s WHERE se.section_id = s.section_id AND s.journal_id = se.journal_id AND s.journal_id = ?',
			(int) $journalId
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$section = $this->_returnSectionFromRow($row);
			if (!isset($returner[$row['editor_id']])) {
				$returner[$row['editor_id']] = array($section);
			} else {
				$returner[$row['editor_id']][] = $section;
			}
			$result->MoveNext();
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all sections in which articles are currently published in
	 * the given issue.
	 * @return array
	 */
	function getByIssueId($issueId) {
		$result = $this->retrieve(
			'SELECT DISTINCT s.*, COALESCE(o.seq, s.seq) AS section_seq FROM sections s, published_articles pa, articles a LEFT JOIN custom_section_orders o ON (a.section_id = o.section_id AND o.issue_id = ?) WHERE s.section_id = a.section_id AND pa.article_id = a.article_id AND pa.issue_id = ? ORDER BY section_seq',
			array((int) $issueId, (int) $issueId)
		);

		$returner = array();
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$returner[] = $this->_returnSectionFromRow($row);
			$result->MoveNext();
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all sections for a journal.
	 * @return DAOResultFactory containing Sections ordered by sequence
	 */
	function getByJournalId($journalId, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT * FROM sections WHERE journal_id = ? ORDER BY seq',
			(int) $journalId, $rangeInfo
		);

		return new DAOResultFactory($result, $this, '_returnSectionFromRow');
	}

	/**
	 * Retrieve all sections.
	 * @return DAOResultFactory containing Sections ordered by journal ID and sequence
	 */
	function getAll($rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT * FROM sections ORDER BY journal_id, seq',
			false, $rangeInfo
		);

		return new DAOResultFactory($result, $this, '_returnSectionFromRow');
	}

	/**
	 * Retrieve all empty (without articles) section ids for a journal.
	 * @return array
	 */
	function getEmptyByJournalId($journalId) {
		$result = $this->retrieve(
			'SELECT s.section_id FROM sections s LEFT JOIN articles a ON (a.section_id = s.section_id) WHERE a.section_id IS NULL AND s.journal_id = ?',
			(int) $journalId
		);

		$returner = array();
		while (!$result->EOF) {
			$returner[] = $result->fields[0];
			$result->MoveNext();
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve the IDs and titles of the sections for a journal in an associative array.
	 * @return array
	 */
	function &getSectionTitles($journalId, $submittableOnly = false) {
		$sections = array();

		$sectionsIterator = $this->getByJournalId($journalId);
		while ($section = $sectionsIterator->next()) {
			if ($submittableOnly) {
				if (!$section->getEditorRestricted()) {
					$sections[$section->getId()] = $section->getLocalizedTitle();
				}
			} else {
				$sections[$section->getId()] = $section->getLocalizedTitle();
			}
		}

		return $sections;
	}

	/**
	 * Check if a section exists with the specified ID.
	 * @param $sectionId int
	 * @param $journalId int
	 * @return boolean
	 */
	function sectionExists($sectionId, $journalId) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM sections WHERE section_id = ? AND journal_id = ?',
			array((int) $sectionId, (int) $journalId)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Sequentially renumber sections in their sequence order.
	 * @param $journalId int
	 */
	function resequenceSections($journalId) {
		$result = $this->retrieve(
			'SELECT section_id FROM sections WHERE journal_id = ? ORDER BY seq',
			(int) $journalId
		);

		for ($i=1; !$result->EOF; $i++) {
			list($sectionId) = $result->fields;
			$this->update(
				'UPDATE sections SET seq = ? WHERE section_id = ?',
				array(
					$i,
					$sectionId
				)
			);

			$result->MoveNext();
		}
		$result->Close();
	}

	/**
	 * Get the ID of the last inserted section.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('sections', 'section_id');
	}

	/**
	 * Delete the custom ordering of an issue's sections.
	 * @param $issueId int
	 */
	function deleteCustomSectionOrdering($issueId) {
		return $this->update(
			'DELETE FROM custom_section_orders WHERE issue_id = ?', (int) $issueId
		);
	}

	/**
	 * Delete a section from the custom section order table.
	 * @param $issueId int
	 * @param $sectionId int
	 */
	function deleteCustomSection($issueId, $sectionId) {
		$seq = $this->getCustomSectionOrder($issueId, $sectionId);

		$this->update(
			'DELETE FROM custom_section_orders WHERE issue_id = ? AND section_id = ?',
			array((int) $issueId, (int) $sectionId)
		);

		// Reduce the section order of every successive section by one
		$this->update(
			'UPDATE custom_section_orders SET seq = seq - 1 WHERE issue_id = ? AND seq > ?',
			array((int) $issueId, (float) $seq)
		);
	}

	/**
	 * Sequentially renumber custom section orderings in their sequence order.
	 * @param $issueId int
	 */
	function resequenceCustomSectionOrders($issueId) {
		$result = $this->retrieve(
			'SELECT section_id FROM custom_section_orders WHERE issue_id = ? ORDER BY seq',
			(int) $issueId
		);

		for ($i=1; !$result->EOF; $i++) {
			list($sectionId) = $result->fields;
			$this->update(
				'UPDATE custom_section_orders SET seq = ? WHERE section_id = ? AND issue_id = ?',
				array($i, $sectionId, (int) $issueId)
			);

			$result->MoveNext();
		}
		$result->Close();
	}

	/**
	 * Check if an issue has custom section ordering.
	 * @param $issueId int
	 * @return boolean
	 */
	function customSectionOrderingExists($issueId) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM custom_section_orders WHERE issue_id = ?',
			(int) $issueId
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 0 ? false : true;
		$result->Close();
		return $returner;
	}

	/**
	 * Get the custom section order of a section.
	 * @param $issueId int
	 * @param $sectionId int
	 * @return int
	 */
	function getCustomSectionOrder($issueId, $sectionId) {
		$result = $this->retrieve(
			'SELECT seq FROM custom_section_orders WHERE issue_id = ? AND section_id = ?',
			array((int) $issueId, (int) $sectionId)
		);

		$returner = null;
		if (!$result->EOF) {
			list($returner) = $result->fields;
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Import the current section orders into the specified issue as custom
	 * issue orderings.
	 * @param $issueId int
	 */
	function setDefaultCustomSectionOrders($issueId) {
		$result = $this->retrieve(
			'SELECT s.section_id FROM sections s, issues i WHERE i.journal_id = s.journal_id AND i.issue_id = ? ORDER BY seq',
			(int) $issueId
		);

		for ($i=1; !$result->EOF; $i++) {
			list($sectionId) = $result->fields;
			$this->insertCustomSectionOrder($issueId, $sectionId, $i);
			$result->MoveNext();
		}

		$result->Close();
	}

	/**
	 * INTERNAL USE ONLY: Insert a custom section ordering
	 * @param $issueId int
	 * @param $sectionId int
	 * @param $seq int
	 */
	function insertCustomSectionOrder($issueId, $sectionId, $seq) {
		$this->update(
			'INSERT INTO custom_section_orders (section_id, issue_id, seq) VALUES (?, ?, ?)',
			array((int) $sectionId,(int) $issueId, (float) $seq)
		);
	}

	/**
	 * Move a custom issue ordering up or down, resequencing as necessary.
	 * @param $issueId int
	 * @param $sectionId int
	 * @param $newPos int The new position (0-based) of this section
	 * @param $up boolean Whether we're moving the section up or down
	 */
	function moveCustomSectionOrder($issueId, $sectionId, $newPos, $up) {
		$this->update(
			'UPDATE custom_section_orders SET seq = ? ' . ($up?'-':'+') . ' 0.5 WHERE issue_id = ? AND section_id = ?',
			array((float) $newPos, (int) $issueId, (int) $sectionId)
		);
		$this->resequenceCustomSectionOrders($issueId);
	}
}

?>
