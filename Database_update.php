<?php
// Pull in PHP Simple HTML DOM Parser
defined('BASEPATH') OR exit('No direct script access allowed');

class Database_update extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 */
    public function index() {
        $this->load->library('simple_html_dom') or die("Need simple_html_dom library");
        $this->load->database();

        $html = new simple_html_dom();
        $html->load_file("http://test.ufandshands.org/health-topic-xml");

        $title_array = array();
        $title_symptoms = array();

        // Store all of the titles that have a subsequent body tag in a title array
        foreach ($html->find('node') as $node) {
            $hasTitle = False;
            $hasBody = False;
            $addedTitle = False;

            // Check if the node has both a title and a body child
            foreach ($node->children() as $nodeChild) {
                if ($nodeChild->tag == 'title') {
                    $hasTitle = True;
                    $title = $nodeChild->plaintext;
                }

                if ($nodeChild->tag == 'body') {
                    $hasBody = True;
                }
                // If both title and body are found in this node, add to the
                // title array the current title
                if ($hasTitle && $hasBody && !$addedTitle) {
                    array_push($title_array, $title);
                    $addedTitle = True;
                }
            }


        }

        // Create an iterator for body tags. Each title has a subsequent 'Body' tag
        $bodyIterator = 0;
        $bodyTagIterator = 1;

        // Find each 'Body' tag
        foreach ($html->find('Body') as $body) {

            // Loop through the child tags in the 'Body' tag
            for ($i = 0; $i < count($body->children()); $i++) {

                // Initialize a blank string to hold all of the symptoms
                $symptomString = "";

                // Initialize a bool to hold wheter we have found a ul tag
                // under a symptoms heading.
                $foundUl = false;

                // If the child tag has the text of 'Symptoms', find the 'ul'
                // after this tag and store it
                if ($body->children($i)->plaintext == 'Symptoms') {

                    // Create an array of all the tags under the Symptoms <h2> tag
                    $tagArray = [];
                    // Grab all the tags until the next <h2> tag. The reasoning
                    // behind this array is below in the 'elseif' statement.
                    while ($body->children($i + $bodyTagIterator)->tag != 'h2') {
                        array_push($tagArray, $body->children($i + $bodyTagIterator)->tag );
                        $bodyTagIterator++;
                    }

                    $bodyTagIterator = 1;

                    // Loop over all child elements until the next <h2> tag is found
                    while ($body->children($i+$bodyTagIterator)->tag != 'h2') {

                        // Grab the content from all of the lists under the
                        // Symtptom <h2> tag. All of these lists contain
                        // possible symptoms
                        if (in_array("ul", $tagArray)) {
                            if ($body->children($i + $bodyTagIterator)->tag == 'ul') {

                                $fouldUl = true;

                                // Now that we have found a <ul> tag, populate the
                                // symptom string with the <li> elements inside
                                foreach ($body->children($i + $bodyTagIterator)->children() as $listElement) {
                                    // Separate each element by comma
                                    $symptomString .= ", " . $listElement->plaintext;
                                }

                            }
                        }
                        elseif (in_array("p", $tagArray) && !in_array("ul", $tagArray)) {
                            // In some cases when parsing there will be a
                            // "Symptoms" tag and then no 'ul' items underneath.
                            // In that case we will grab the p tags that follow
                            // the "Symptoms" <h2> tag until the next <h2> tag.
                            // This will only happen though if there are no
                            // 'ul' tags in the array, hence the tag array.
                            if ($body->children($i + $bodyTagIterator)->tag == 'p') {

                                // Now that we have found a <p> tag, populate the
                                // symptom string with the text inside
                                $symptomString .= ". " .  $body->children($i + $bodyTagIterator)->plaintext;

                                }
                        }

                        // Trim the trailing and leading comma or period off the symptom string
                        $symptomString = ltrim($symptomString, ', ');
                        $symptomString = ltrim($symptomString, '. ');

                        $symptomString = rtrim($symptomString, ', ');

                        // Populate the title/symptom array with a key of the
                        // title and a value of its cooresponding symptom
                        // string
                        $title_symptoms[$title_array[$bodyIterator]] = $symptomString;

                        // Increment the body tag iteratory to look at the next
                        // tag element under this current <Body> tag
                        $bodyTagIterator++;
                    }
                    $i++;
                    $bodyTagIterator = 1;
                }
            }
            $bodyIterator++;
        }



        // Populate Database if empty
        $count = $this->db->query("SELECT COUNT(*) FROM conditions");
        if ($count == 0) {
            foreach ($title_symptoms as $title => $symptoms) {
                $data = array (
                    'name' => $title,
                    'symptoms' => $symptoms,
                );
                $this->db->insert('conditions', $data);
            }
        }

        $data['title_symptoms'] = $title_symptoms;
        $this->load->view('templates/header', $data);
        $this->load->view('templates/database', $data);
    }
}

