<?php

class Author_Collection_TaxonomyMapper extends Moxca_Taxonomy_TaxonomyMapper
{

    protected $db;
    protected $identityMap;

    function __construct()
    {
        $this->db = Zend_Registry::get('db');
        $this->identityMap = new SplObjectStorage;
    }

    public function insertWorkKeyword($termId)
    {

        $query = $this->db->prepare("INSERT INTO moxca_terms_taxonomy (term_id, taxonomy, count)
            VALUES (:termId, 'work_keyword', 0)");

        $query->bindValue(':termId', $termId, PDO::PARAM_INT);

        $query->execute();

        return (int)$this->db->lastInsertId();


    }


    public function insertTheme($termId)
    {

        $query = $this->db->prepare("INSERT INTO moxca_terms_taxonomy (term_id, taxonomy, count)
            VALUES (:termId, 'theme', 0)");

        $query->bindValue(':termId', $termId, PDO::PARAM_INT);

        $query->execute();

        return (int)$this->db->lastInsertId();


    }

    public function existsTheme($termId)
    {
        $query = $this->db->prepare("SELECT id FROM moxca_terms_taxonomy WHERE term_id = :termId AND taxonomy = 'theme';");

        $query->bindValue(':termId', $termId, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetch();

        if (!empty($result)) {
            //$row = current($result);
            return $result['id'];
        } else {
            return false;
        }
    }

    public function insertCharacter($termId)
    {

        $query = $this->db->prepare("INSERT INTO moxca_terms_taxonomy (term_id, taxonomy, count)
            VALUES (:termId, 'character', 0)");

        $query->bindValue(':termId', $termId, PDO::PARAM_INT);

        $query->execute();

        return (int)$this->db->lastInsertId();


    }

    public function existsCharacter($termId)
    {
        $query = $this->db->prepare("SELECT id FROM moxca_terms_taxonomy WHERE term_id = :termId AND taxonomy = 'character';");

        $query->bindValue(':termId', $termId, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetch();

        if (!empty($result)) {
            //$row = current($result);
            return $result['id'];
        } else {
            return false;
        }
    }

    public function existsWorkKeyword($termId)
    {
        $query = $this->db->prepare("SELECT id FROM moxca_terms_taxonomy WHERE term_id = :termId AND taxonomy = 'work_keyword';");

        $query->bindValue(':termId', $termId, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetch();

        if (!empty($result)) {
            //$row = current($result);
            return $result['id'];
        } else {
            return false;
        }
    }

    public function updateWorkCharactersRelationships(Author_Collection_Work $obj)
    {
        $newCharacters = $obj->getCharacters();
        $workId = $obj->getId();
        $formerCharacters = $this->workHasCharacters($workId);

        if ((is_array($newCharacters)) && (count($newCharacters))) {

            if (!$formerCharacters) {
                // tudo novo, é só incluir
                if (count($newCharacters) > 0) {
                    foreach($newCharacters as $k => $termId) {
                        $termTaxonomyId = $this->createCharacterIfNeeded($termId);
                        $this->insertRelationship($workId, $termTaxonomyId);
                    }
                }
            } else {
                //descobre se caiu algum
                //   e remove
                $toRemove = array_diff($formerCharacters, $newCharacters);
                if ((is_array($toRemove)) && (count($toRemove))) {
                    foreach($toRemove as $k => $termId) {
                        if ($taxonomyId = $this->createCharacterIfNeeded($termId)) {
                            $this->deleteRelationship($workId, $termId, 'character');
                            $this->decreaseTermTaxonomyCount($taxonomyId, 1);
                        }
                    }
                }
                //descobre quais são novos
                //    e inclui
                $toInclude = array_diff($newCharacters, $formerCharacters);
                if ((is_array($toInclude)) && (count($toInclude))) {
                    foreach($toInclude as $k => $termId) {
                        $termTaxonomyId = $this->createCharacterIfNeeded($termId);
                        $this->insertRelationship($workId, $termTaxonomyId);
                    }
                }

                if ($newCharacters != $formerCharacters) {
                    $formerTermTaxonomy = $this->createCharacterIfNeeded($formerCharacters);
                    $newTermTaxonomy = $this->createCharacterIfNeeded($newCharacters);

                    $query = $this->db->prepare("UPDATE moxca_terms_relationships SET term_taxonomy = :newCharacter"
                            . " WHERE object = :workId AND term_taxonomy = :formerCharacter;");

                    $query->bindValue(':workId', $workId, PDO::PARAM_STR);
                    $query->bindValue(':newCharacter', $newTermTaxonomy, PDO::PARAM_STR);
                    $query->bindValue(':formerCharacter', $formerTermTaxonomy, PDO::PARAM_STR);
                    $query->execute();


                    $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = count + 1
                        WHERE id = :termTaxonomy;");
                    $query->bindValue(':termTaxonomy', $newTermTaxonomy, PDO::PARAM_STR);
                    $query->execute();

                    $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = count - 1
                        WHERE id = :termTaxonomy;");
                    $query->bindValue(':termTaxonomy', $formerTermTaxonomy, PDO::PARAM_STR);

                    try {
                        $query->execute();
                    } catch (Exception $e) {
                        $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = 0
                            WHERE id = :termTaxonomy;");
                        $query->bindValue(':termTaxonomy', $formerTermTaxonomy, PDO::PARAM_STR);
                    }
                }
            }
        } else {
            //remove todos
        }

    }

    public function updateWorkKeywordsRelationships(Author_Collection_Work $obj)
    {
        $newKeywords = $obj->getKeywords();
        $workId = $obj->getId();
        $formerKeywords = $this->workHasKeywords($workId);

        if ((is_array($newKeywords)) && (count($newKeywords))) {

            if (!$formerKeywords) {
                // tudo novo, é só incluir
                if (count($newKeywords) > 0) {
                    $this->insertListOfKeywords($workId, $newKeywords);
                }
            } else {
                //descobre se caiu algum
                //   e remove
                $toRemove = array_diff($formerKeywords, $newKeywords);
                if ((is_array($toRemove)) && (count($toRemove))) {
                    foreach($toRemove as $k => $termId) {
                        if ($taxonomyId = $this->createWorkKeywordIfNeeded($termId)) {
                            $this->deleteRelationship($workId, $termId, 'work_keyword');
                            $this->decreaseTermTaxonomyCount($taxonomyId, 1);
                        }
                    }
                }

                //descobre quais são novos
                //    e inclui
                $toInclude = array_diff($newKeywords, $formerKeywords);
                if ((is_array($toInclude)) && (count($toInclude))) {
                    $this->insertListOfKeywords($workId, $toInclude);
                }

                if ($newKeywords != $formerKeywords) {
                    $formerTermTaxonomy = $this->createWorkKeywordIfNeeded($formerKeywords);
                    $newTermTaxonomy = $this->createWorkKeywordIfNeeded($newKeywords);

                    $query = $this->db->prepare("UPDATE moxca_terms_relationships SET term_taxonomy = :newKeyword"
                            . " WHERE object = :workId AND term_taxonomy = :formerKeyword;");

                    $query->bindValue(':workId', $workId, PDO::PARAM_STR);
                    $query->bindValue(':newKeyword', $newTermTaxonomy, PDO::PARAM_STR);
                    $query->bindValue(':formerKeyword', $formerTermTaxonomy, PDO::PARAM_STR);
                    $query->execute();


                    $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = count + 1
                        WHERE id = :termTaxonomy;");
                    $query->bindValue(':termTaxonomy', $newTermTaxonomy, PDO::PARAM_STR);
                    $query->execute();

                    $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = count - 1
                        WHERE id = :termTaxonomy;");
                    $query->bindValue(':termTaxonomy', $formerTermTaxonomy, PDO::PARAM_STR);

                    try {
                        $query->execute();
                    } catch (Exception $e) {
                        $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = 0
                            WHERE id = :termTaxonomy;");
                        $query->bindValue(':termTaxonomy', $formerTermTaxonomy, PDO::PARAM_STR);
                    }
                }
            }
        } else {
            //remove todos
        }

    }

    public function updateWorkThemesRelationships(Author_Collection_Work $obj)
    {
        $newThemes = $obj->getThemes();
        $workId = $obj->getId();
        $formerThemes = $this->workHasThemes($workId);

        if ((is_array($newThemes)) && (count($newThemes))) {

            if (!$formerThemes) {
                // tudo novo, é só incluir
                if (count($newThemes) > 0) {
                    foreach($newThemes as $k => $termId) {
                        $termTaxonomyId = $this->createThemeIfNeeded($termId);
                        $this->insertRelationship($workId, $termTaxonomyId);
                    }
                }
            } else {
                //descobre se caiu algum
                //   e remove
                $toRemove = array_diff($formerThemes, $newThemes);
                if ((is_array($toRemove)) && (count($toRemove))) {
                    foreach($toRemove as $k => $termId) {
                        if ($taxonomyId = $this->createThemeIfNeeded($termId)) {
                            $this->deleteRelationship($workId, $termId, 'theme');
                            $this->decreaseTermTaxonomyCount($taxonomyId, 1);
                        }
                    }
                }

                //descobre quais são novos
                //    e inclui
                $toInclude = array_diff($newThemes, $formerThemes);
                if ((is_array($toInclude)) && (count($toInclude))) {
                    foreach($toInclude as $k => $termId) {
                        $termTaxonomyId = $this->createThemeIfNeeded($termId);
                        $this->insertRelationship($workId, $termTaxonomyId);
                    }
                }

                if ($newThemes != $formerThemes) {
                    $formerTermTaxonomy = $this->createThemeIfNeeded($formerThemes);
                    $newTermTaxonomy = $this->createThemeIfNeeded($newThemes);

                    $query = $this->db->prepare("UPDATE moxca_terms_relationships SET term_taxonomy = :newTheme"
                            . " WHERE object = :workId AND term_taxonomy = :formerTheme;");

                    $query->bindValue(':workId', $workId, PDO::PARAM_STR);
                    $query->bindValue(':newTheme', $newTermTaxonomy, PDO::PARAM_STR);
                    $query->bindValue(':formerTheme', $formerTermTaxonomy, PDO::PARAM_STR);
                    $query->execute();


                    $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = count + 1
                        WHERE id = :termTaxonomy;");
                    $query->bindValue(':termTaxonomy', $newTermTaxonomy, PDO::PARAM_STR);
                    $query->execute();

                    $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = count - 1
                        WHERE id = :termTaxonomy;");
                    $query->bindValue(':termTaxonomy', $formerTermTaxonomy, PDO::PARAM_STR);

                    try {
                        $query->execute();
                    } catch (Exception $e) {
                        $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = 0
                            WHERE id = :termTaxonomy;");
                        $query->bindValue(':termTaxonomy', $formerTermTaxonomy, PDO::PARAM_STR);
                    }
                }
            }
        } else {
            //remove todos
        }

    }

    public function getAllCharactersAlphabeticallyOrdered($active=false)
    {
        if($active) {
            $justActive = " AND tx.count > 0 ";
        } else {
            $justActive = "";
        }

        $query = $this->db->prepare('SELECT t.id, t.term, t.uri
                FROM moxca_terms t
                LEFT JOIN moxca_terms_taxonomy tx ON t.id = tx.term_id
                WHERE tx.taxonomy = \'character\'' .  $justActive . ' ORDER BY t.term');
        $query->execute();
        $resultPDO = $query->fetchAll();
        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['id']] = array('uri' => $row['uri'], 'term' => $row['term']);
        }
        return $data;

    }

    public function getAllWorksKeywordsAlphabeticallyOrdered()
    {
        $query = $this->db->prepare('SELECT t.id, t.term, t.uri, tx.count
                FROM moxca_terms t
                LEFT JOIN moxca_terms_taxonomy tx ON t.id = tx.term_id
                WHERE tx.taxonomy =  \'work_keyword\' ORDER BY t.term');
        $query->execute();
        $resultPDO = $query->fetchAll();
        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['id']] = array('term' => $row['term'], 'uri' => $row['uri'], 'count' => $row['count']);
        }
        return $data;

    }


    public function getAllThemesAlphabeticallyOrdered()
    {
        $query = $this->db->prepare('SELECT t.id, t.term, t.uri
                FROM moxca_terms t
                LEFT JOIN moxca_terms_taxonomy tx ON t.id = tx.term_id
                WHERE tx.taxonomy =  \'theme\' ORDER BY t.term');
        $query->execute();
        $resultPDO = $query->fetchAll();
        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['id']] = array('uri' => $row['uri'], 'term' => $row['term']);
        }
        return $data;

    }



    public function workHasKeywords($workId)
    {
        $query = $this->db->prepare('SELECT tx.id, tx.term_id
                FROM moxca_terms_relationships tr
                LEFT JOIN moxca_terms_taxonomy tx ON tr.term_taxonomy = tx.id
                WHERE tr.object = :workId
                AND tx.taxonomy =  \'work_keyword\'');

        $query->bindValue(':workId', $workId, PDO::PARAM_INT);
        $query->execute();
        $resultPDO = $query->fetchAll();

        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['id']] = $row['term_id'];
        }
        return $data;
    }

    public function workHasCharacters($workId)
    {
        $query = $this->db->prepare('SELECT tx.id, tx.term_id
                FROM moxca_terms_relationships tr
                LEFT JOIN moxca_terms_taxonomy tx ON tr.term_taxonomy = tx.id
                WHERE tr.object = :workId
                AND tx.taxonomy =  \'character\'');

        $query->bindValue(':workId', $workId, PDO::PARAM_INT);
        $query->execute();
        $resultPDO = $query->fetchAll();

        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['id']] = $row['term_id'];
        }
        return $data;
    }

    public function workHasThemes($workId)
    {
        $query = $this->db->prepare('SELECT tx.id, tx.term_id
                FROM moxca_terms_relationships tr
                LEFT JOIN moxca_terms_taxonomy tx ON tr.term_taxonomy = tx.id
                WHERE tr.object = :workId
                AND tx.taxonomy =  \'theme\'');

        $query->bindValue(':workId', $workId, PDO::PARAM_INT);
        $query->execute();
        $resultPDO = $query->fetchAll();

        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['id']] = $row['term_id'];
        }
        return $data;
    }


    private function createWorkKeywordIfNeeded($termId)
    {
        $existsKeywordWithTerm = $this->existsWorkKeyword($termId);
        if (!$existsKeywordWithTerm) {
            $existsKeywordWithTerm = $this->insertWorkKeyword($termId);
        }

        return $existsKeywordWithTerm;

    }

    private function createThemeIfNeeded($termId)
    {
        $existsThemeWithTerm = $this->existsTheme($termId);
        if (!$existsThemeWithTerm) {
            $existsThemeWithTerm = $this->insertTheme($termId);
        }

        return $existsThemeWithTerm;

    }

    private function createCharacterIfNeeded($termId)
    {
        $existsCharacterWithTerm = $this->existsCharacter($termId);
        if (!$existsCharacterWithTerm) {
            $existsCharacterWithTerm = $this->insertCharacter($termId);
        }

        return $existsCharacterWithTerm;

    }

    public function findThemeByWorkId($id)
    {

        $query = $this->db->prepare('SELECT tx.term_id
                FROM moxca_terms_relationships tr
                LEFT JOIN moxca_terms_taxonomy tx ON tr.term_taxonomy = tx.id
                WHERE tr.object = :id
                AND tx.taxonomy =  \'theme\'');
        $query->bindValue(':id', $id, PDO::PARAM_STR);
        $query->execute();

        $result = $query->fetch();

        if (empty($result)) {
            $termId = null;
        } else {
            $termId = $result['term_id'];
        }

        return $termId;


    }

    public function findTaxonomyByTheme($id)
    {

        $query = $this->db->prepare('SELECT id FROM moxca_terms_taxonomy tx
                WHERE tx.term_id = :id
                AND tx.taxonomy =  \'theme\'');
        $query->bindValue(':id', $id, PDO::PARAM_STR);
        $query->execute();

        $result = $query->fetch();

        if (empty($result)) {
            $taxonomyId = null;
        } else {
            $taxonomyId = $result['id'];
        }

        return $taxonomyId;


    }

    public function worksWithTheme($theme)
    {
        $query = $this->db->prepare('SELECT tr.object
                FROM moxca_terms_relationships tr
                LEFT JOIN moxca_terms_taxonomy tx ON tr.term_taxonomy = tx.id
                LEFT JOIN moxca_terms tt ON tx.term_id = tt.id
                WHERE tt.term = :theme
                AND tx.taxonomy =  \'theme\'');

        $query->bindValue(':theme', $theme, PDO::PARAM_STR);
        $query->execute();

        $resultPDO = $query->fetchAll();

        $data = array();
        foreach ($resultPDO as $row) {
            $data[] = $row['object'];
        }
        return $data;
    }

    public function editionsWithCharacter($keyword)
    {
        $query = $this->db->prepare('SELECT e.id
                FROM author_collection_editions e
                LEFT JOIN author_collection_works w ON e.work = w.id
                LEFT JOIN moxca_terms_relationships tr ON w.id = tr.object
                LEFT JOIN moxca_terms_taxonomy tx ON tr.term_taxonomy = tx.id
                LEFT JOIN moxca_terms tt ON tx.term_id = tt.id
                WHERE tt.uri= :keyword
                AND tx.taxonomy =  \'character\'');

        $query->bindValue(':keyword', $keyword, PDO::PARAM_STR);
        $query->execute();

        $resultPDO = $query->fetchAll();

        $data = array();
        foreach ($resultPDO as $row) {
            $data[] = $row['id'];
        }
        return $data;
    }

    public function editionsWithKeyword($keyword)
    {
        $query = $this->db->prepare('SELECT e.id
                FROM author_collection_editions e
                LEFT JOIN author_collection_works w ON e.work = w.id
                LEFT JOIN moxca_terms_relationships tr ON w.id = tr.object
                LEFT JOIN moxca_terms_taxonomy tx ON tr.term_taxonomy = tx.id
                LEFT JOIN moxca_terms tt ON tx.term_id = tt.id
                WHERE tt.uri= :keyword
                AND tx.taxonomy =  \'work_keyword\'
                ORDER BY e.title');

        $query->bindValue(':keyword', $keyword, PDO::PARAM_STR);
        $query->execute();

        $resultPDO = $query->fetchAll();

        $data = array();
        foreach ($resultPDO as $row) {
            $data[] = $row['id'];
        }
        return $data;
    }

    public function editionsWithTheme($theme)
    {
        $query = $this->db->prepare('SELECT e.id
                FROM author_collection_editions e
                LEFT JOIN author_collection_works w ON e.work = w.id
                LEFT JOIN moxca_terms_relationships tr ON w.id = tr.object
                LEFT JOIN moxca_terms_taxonomy tx ON tr.term_taxonomy = tx.id
                LEFT JOIN moxca_terms tt ON tx.term_id = tt.id
                WHERE tt.uri = :theme
                AND tx.taxonomy =  \'theme\'
                ORDER BY w.title');

        $query->bindValue(':theme', $theme, PDO::PARAM_STR);
        $query->execute();

        $resultPDO = $query->fetchAll();

        $data = array();
        foreach ($resultPDO as $row) {
            $data[] = $row['id'];
        }
        return $data;
    }

    public function deleteKeyword($workId, $termId)
    {

        try {
            if ($taxonomyId = $this->createWorkKeywordIfNeeded($termId)) {
                $this->deleteRelationship($workId, $termId, 'keyword');
                $this->decreaseTermTaxonomyCount($taxonomyId, 1);
                return true;
            }
        } catch (Exception $ex) {
            throw $ex;
        }
        return false;

    }

    public function getKeywordsAlphabeticallyOrdered($justRelatedToWorks=false)
    {

        $countCondition = "";
        if ($justRelatedToWorks) {
            $countCondition = " AND tx.count > 0 ";
        }
        $query = $this->db->prepare("SELECT t.id, t.term, t.uri
                FROM moxca_terms t
                LEFT JOIN moxca_terms_taxonomy tx ON t.id = tx.term_id
                WHERE tx.taxonomy =  'keyword' $countCondition ORDER BY t.term");
        $query->execute();
        $resultPDO = $query->fetchAll();
        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['uri']] = $row['term'];
        }
        return $data;


    }

    public function getKeywordsRelatedToWork($work)
    {

        $query = $this->db->prepare("SELECT t.id, t.term, t.uri
                FROM moxca_terms t
                LEFT JOIN moxca_terms_taxonomy tx ON t.id = tx.term_id
                LEFT JOIN moxca_terms_relationships tr ON tx.id = tr.term_taxonomy
                WHERE tx.taxonomy ='work_keyword'
                AND tr.object = :object ORDER BY t.term");
        $query->bindValue(':object', $work, PDO::PARAM_INT);
        $query->execute();
        $resultPDO = $query->fetchAll();
        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['uri']] = $row['term'];
        }
        return $data;


    }

    public function getThemesRelatedToWork($work)
    {

        $query = $this->db->prepare("SELECT t.id, t.term, t.uri
                FROM moxca_terms t
                LEFT JOIN moxca_terms_taxonomy tx ON t.id = tx.term_id
                LEFT JOIN moxca_terms_relationships tr ON tx.id = tr.term_taxonomy
                WHERE tx.taxonomy ='theme'
                AND tr.object = :object ORDER BY t.term");
        $query->bindValue(':object', $work, PDO::PARAM_INT);
        $query->execute();
        $resultPDO = $query->fetchAll();
        $data = array();
        foreach ($resultPDO as $row) {
            $data[$row['uri']] = $row['term'];
        }
        return $data;


    }


    private function insertListOfKeywords($workId, $keywordsArray)
    {
        foreach($keywordsArray as $k => $termId) {
            $termTaxonomyId = $this->createWorkKeywordIfNeeded($termId);
            $this->insertRelationship($workId, $termTaxonomyId);
        }
    }

    public function deleteCharacter($workId, $termId)
    {

        try {
            if ($taxonomyId = $this->createCharacterIfNeeded($termId)) {
                $this->deleteRelationship($workId, $termId, 'character');
                $this->decreaseTermTaxonomyCount($taxonomyId, 1);
                return true;
            }
        } catch (Exception $ex) {
            throw $ex;
        }
        return false;

    }

    public function deleteTheme($workId, $termId)
    {

        try {
            if ($taxonomyId = $this->createWorkKeywordIfNeeded($termId)) {
                $this->deleteRelationship($workId, $termId, 'theme');
                $this->decreaseTermTaxonomyCount($taxonomyId, 1);
                return true;
            }
        } catch (Exception $ex) {
            throw $ex;
        }
        return false;

    }


 private function faxinaCount()
 {

        $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = 0 WHERE 1");
        $query->execute();

        $query = $this->db->prepare("SELECT term_taxonomy, count(term_taxonomy) as count FROM `moxca_terms_relationships` WHERE 1 GROUP BY term_taxonomy");
        $query->execute();
        $resultPDO = $query->fetchAll();


        foreach ($resultPDO as $key => $row) {
            $query = $this->db->prepare("UPDATE moxca_terms_taxonomy SET count = :newCount"
                    . " WHERE id = :id;");

            $query->bindValue(':newCount', $row['count'], PDO::PARAM_STR);
            $query->bindValue(':id', $row['term_taxonomy'], PDO::PARAM_STR);
            $query->execute();
        }
        $query = $this->db->prepare("DELETE FROM moxca_terms_taxonomy WHERE count < 1");
        $query->execute();


 }



}