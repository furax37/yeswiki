<?php
/** afficher_image_attach() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut
 *
 * @param   string	nom du fichier image
 * @param   string	label pour l'image
 * @param   string	classes html supplementaires
 * @param   int		largeur en pixel de la vignette
 * @param   int		hauteur en pixel de la vignette
 * @param   int		largeur en pixel de l'image redimensionnee
 * @param   int		hauteur en pixel de l'image redimensionnee
 * @return  html    affichage a l'ecran
 */
function afficher_image_attach($idfiche, $nom_image, $label, $class, $largeur_vignette, $hauteur_vignette)
{
    $oldpage = $GLOBALS['wiki']->GetPageTag();
    $GLOBALS['wiki']->tag = $idfiche;
    $GLOBALS['wiki']->page['time'] = date('YmdHis');
    $GLOBALS['wiki']->setParameter("desc", $label);
    $GLOBALS['wiki']->setParameter("file", $nom_image);   
    $GLOBALS['wiki']->setParameter("class", $class);
    $GLOBALS['wiki']->setParameter("width", $largeur_vignette);  
    $GLOBALS['wiki']->setParameter("height", $hauteur_vignette); 
    if (!class_exists('attach')){
        include('tools/attach/actions/attach.class.php');
    }
    $attach = new Attach($GLOBALS['wiki']);
    ob_start();
    $attach->doAttach();  
    $output = ob_get_contents();
    ob_end_clean();           
    $GLOBALS['wiki']->tag = $oldpage;

    $output = preg_replace('/width=\".*\".*height=\".*\"/U', '', $output );
    preg_match_all('/(\<img.*\/\>)/U', $output, $matches);
    return $matches[0][0];
}

function sanitizeEntity($string) {
	return htmlspecialchars(strtr(str_replace(array('\\\'','\''),array('_','_'),$string),'/ ���������������������������������������������������',
'__aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'), ENT_COMPAT, TEMPLATES_DEFAULT_CHARSET);

}

function tokenTruncate($string, $your_desired_width) {
  $parts = preg_split('/([\s\n\r]+)/', $string, null, PREG_SPLIT_DELIM_CAPTURE);
  $parts_count = count($parts);

  $length = 0;
  $last_part = 0;
  for (; $last_part < $parts_count; ++$last_part) {
    $length += strlen($parts[$last_part]);
    if ($length > $your_desired_width) { break; }
  }

  return implode(array_slice($parts, 0, $last_part));
}

function get_filtertags_parameters_recursive($nb=1, $tab = array()) {	
	$filter = $GLOBALS['wiki']->GetParameter('filter'.$nb);

	if (empty($filter) && $nb == 1) exit('<div class="alert alert-danger">'.TAGS_NO_FILTERS.'</div>'."\n");
	elseif (empty($filter)) return $tab;
	else {
		if (!isset($tab['tags'])) $tab['tags'] = ''; else $tab['tags'] .= ',';
		$explodelabel = explode(":", $filter);

		// on decoupe le choix pour recuperer le titre
		if (count($explodelabel)> 2) exit('<div class="alert alert-danger">'.TAGS_ONLY_ONE_DOUBLEPOINT.'</div>'."\n");
		elseif (count($explodelabel) == 2) {
			$tab[$nb]['title'] =  '<strong>'.$explodelabel[0].' : </strong>'."\n"; 
			$tab[$nb]['arraytags'] = explode(',', $explodelabel[1]);
		}
		else {
			$tab[$nb]['title'] = '';
			$tab[$nb]['arraytags']  = explode(',', $explodelabel[0]);
		}
		$toggle = $GLOBALS['wiki']->GetParameter('select'.$nb);
		if (!empty($toggle) && $toggle == 'checkbox') $tab[$nb]['toggle'] = $toggle;
		else $tab[$nb]['toggle'] = 'radio';
		$class = $GLOBALS['wiki']->GetParameter('class'.$nb);
		if (!empty($class)) $tab[$nb]['class'] = $class;
		else $tab[$nb]['class'] = 'filter-inline';
		$tab['tags'] .= '"'.implode('","', $tab[$nb]['arraytags']).'"';
		$nb++;
		$tab = get_filtertags_parameters_recursive($nb, $tab);

		return $tab;
	}
}

function afficher_commentaires_recursif($page, $wiki, $premier=true) {
	$output = '';
	$comments = $wiki->LoadComments($page);
	$valcomment['tag'] = $page;
	$valcomment['commentaires'] = array();
	// display comments themselves
	if ($comments) {
		$valcomment=array();
		$i=0;
		foreach ($comments as $comment) {
			$valcomment['commentaires'][$i]['tag'] = $comment["tag"];
			$valcomment['commentaires'][$i]['body'] = $wiki->Format($comment["body"]);
			$valcomment['commentaires'][$i]['infos'] = "de ".$wiki->Format($comment["user"]).", ".date("\l\e d.m.Y &\a\g\\r\av\e; H:i:s", strtotime($comment["time"]));
			$valcomment['commentaires'][$i]['actions'] = '';
			if ($wiki->HasAccess("comment", $comment['tag']))
			{
				$valcomment['commentaires'][$i]['actions'] .= '<a href="'.$wiki->href('', $comment['tag']).'" class="repondre_commentaire">R&eacute;pondre</a> ';
			}
			if ($wiki->HasAccess('write', $comment['tag']) || $wiki->UserIsOwner($comment['tag']) || $wiki->UserIsAdmin($comment['tag']))
			{
				$valcomment['commentaires'][$i]['actions'] .= '<a href="'.$wiki->href('edit', $comment['tag']).'" class="editer_commentaire">Editer</a> ';
			}
			if ($wiki->UserIsOwner($comment['tag']) || $wiki->UserIsAdmin())
			{
				$valcomment['commentaires'][$i]['actions'] .= '<a href="'.$wiki->href('deletepage', $comment['tag']).'" class="supprimer_commentaire">Supprimer</a>'."\n" ;
			}
			$valcomment['commentaires'][$i]['reponses'] = afficher_commentaires_recursif($comment['tag'], $wiki, false);
			$i++;
		}
	} 

	// formulaire d'ajout de commentaire
	$valcomment['commentform'] = '';
	if ($premier && $wiki->HasAccess("comment", $page))	{
		$valcomment['commentform'] .= "<div class=\"microblog-comment-form\">\n" ;
		$valcomment['commentform'] .= $wiki->FormOpen("addcomment", $page).'
				<textarea name="body" class="comment-microblog" rows="3" placeholder="Ecrire votre commentaire ici..."></textarea>
				<button class="btn btn-primary btn-microblog" name="action" value="addcomment">Ajouter votre commentaire</button>'.$wiki->FormClose();
		$valcomment['commentform'] .= "<div class=\"clear\"></div></div>\n" ;
	}

	include_once('squelettephp.class.php');
	$squelcomment = new SquelettePhp('tools/tags/presentation/templates/comment_list.tpl.html');
	$squelcomment->set($valcomment);
	$output .= $squelcomment->analyser();

	return $output;
}

function array_non_empty($array) {
	$retour = array();
	foreach ($array as $a){
		if (!empty($a)) { 
			array_push($retour, $a);
		}
	}
	return $retour;
}

function split_words($string){
$retour = array();
  $delimiteurs = ' .!?, :;(){}[]%';
  $tok = strtok($string, " ");
  while (strlen(join(" ", $retour)) != strlen($string)) {
  array_push($retour, $tok);
  $tok = strtok($delimiteurs);
  }
  return array_non_empty($retour);
}

function utf8_special_decode($matches) {
	return iconv('UCS-4LE','UTF-8',pack('V', hexdec('U'.$matches[1])));
}

function get_title_from_body($page){
	// on recupere les bf_titre ou les titres de niveau 1 et de niveau 2, on met la PageWiki sinon
	preg_match_all("/\"bf_titre\":\"(.*)\"/U", $page['body'], $titles);
	if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0]!='') {
		$title = utf8_decode(preg_replace_callback('/\\\\u([a-f0-9]{4})/', 'utf8_special_decode', $titles[1][0]));
			//preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $titles[1][0]));
	} 
	else {
		preg_match_all("/\={6}(.*)\={6}/U", $page['body'], $titles);
		if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0]!='') {
			$title = $GLOBALS['wiki']->Format(trim($titles[1][0]));
		}
		else {
			preg_match_all("/={5}(.*)={5}/U", $page['body'], $titles);
			if (is_array($titles[1]) && isset($titles[1][0]) && $titles[1][0]!='') {
				$title = $GLOBALS['wiki']->Format(trim($titles[1][0]));
			}
			else {
				$title = $page['tag'];
			}
		}
	}
	return $title;
}

function get_image_from_body($page){
	// on cherche les actions attach avec image, puis les images bazar
	preg_match_all("/\{\{attach.*file=\".*\.(?i)(jpg|png|gif|bmp).*\}\}/U", $page['body'], $images);
	if (is_array($images[0]) && isset($images[0][0]) && $images[0][0]!='') {
		preg_match_all("/.*file=\"(.*\.(?i)(jpg|png|gif|bmp))\".*desc=\"(.*)\".*\}\}/U", $images[0][0], $attachimg);
		$image = afficher_image_attach($page['tag'], $attachimg[1][0], $attachimg[3][0], 'filtered-image', 300, 225) ;
	}
	else {
		preg_match_all("/\"imagebf_image\":\"(.*)\"/U", $page['body'], $image);
		if (is_array($image[1]) && isset($image[1][0]) && $image[1][0]!='') {
			$imagefile = utf8_decode(preg_replace("/\\\\u([a-f0-9]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $image[1][0]));
			$image =  afficher_image($imagefile, $imagefile, 'filtered-image img-responsive', '', '', 300, 225);
		} else {
			preg_match_all("/\[\[(http.*\.(?i)(jpg|png|gif|bmp)) .*\]\]/U", $page['body'], $image);
			if (is_array($image[1]) && isset($image[1][0]) && $image[1][0]!='') {
				$image = $GLOBALS['wiki']->Format('""<img alt="image" class="img-responsive" src="'.trim(str_replace('\\', '', $image[1][0])).'" />""');
			}
			else {
				preg_match_all("/\<img.*src=\"(.*)\"/U", $page['body'], $image);
				if (is_array($image[1]) && isset($image[1][0]) && $image[1][0]!='') {
					$image = $GLOBALS['wiki']->Format('""<img alt="image" class="img-responsive" src="'.trim($image[1][0]).'" />""');
				}
				else {
					$image = '';
				}
			}	
		}	
	}
	return $image;
}

/** generatePageName() Prends une chaine de caracteres, et la tranforme en NomWiki unique, en la limitant a 50 caracteres et en mettant 2 majuscules
*	Si le NomWiki existe deja, on propose recursivement NomWiki2, NomWiki3, etc..
*
*   @param  string  chaine de caracteres avec de potentiels accents a enlever
*   @param	integer	nombre d'iteration pour la fonction recursive (1 par defaut)
*
*
*   return  string	chaine de caracteres, en NomWiki unique
*/
function generatePageName($nom, $occurence = 1)
{
    // si la fonction est appelee pour la premiere fois, on nettoie le nom passe en parametre
    if ($occurence == 1) {
        // les noms wiki ne doivent pas depasser les 50 caracteres, on coupe a 48, histoire de pouvoir ajouter un chiffre derriere si nom wiki deja existant
        // plus traitement des accents
        // plus on met des majuscules au debut de chaque mot et on fait sauter les espaces
        $temp = explode(" ", ucwords(strtolower(preg_replace("/&([a-z])[a-z]+;/i","$1", htmlentities(substr($nom, 0, 47))))));
        $nom = '';
        foreach ($temp as $mot) {
            // on vire d'eventuels autres caracteres speciaux
            $nom .= preg_replace("/[^a-zA-Z0-9]/","",trim($mot));
        }

        // on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
        $var = preg_replace('/[^A-Z]/','',$nom);
        if (strlen($var)<2) {
            $last = ucfirst(substr($nom, strlen($nom) - 1));
            $nom = substr($nom, 0, -1).$last;
        }
    }
    // si on en est a plus de 2 occurences, on supprime le chiffre precedent et on ajoute la nouvelle occurence
    elseif ($occurence>2) {
        $nb = -1*strlen(strval($occurence-1));
        $nom = substr($nom, 0, $nb).$occurence;
    }
    // cas ou l'occurence est la deuxieme : on reprend le NomWiki en y ajoutant le chiffre 2
    else {
        $nom = $nom.$occurence;
    }

     // on verifie que la page n'existe pas deja : si c'est le cas on le retourne
    if (!is_array($GLOBALS['wiki']->LoadPage($nom))) {
        return $nom;
    }
    // sinon, on rappele recursivement la fonction jusqu'a ce que le nom aille bien
    else {
        $occurence++;

        return genere_nom_wiki($nom, $occurence);
    }
}

?>