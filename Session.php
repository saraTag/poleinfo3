<?php
  require_once ("Formulaire.class.php");

//	date_default_timezone_set('Europe/Paris');

  // Search a session

  function GetSession ($id_session, $bd) 
  {
    $query =  "SELECT * FROM Session WHERE id_session = '$id_session' " ;
    $resultat = $bd->execRequete ($query);
    return $bd->objetSuivant ($resultat);
  }

  // V�rification qu'une session est valide

  function SessionValide ($session, $bd)
  {
    // V�rifions que le temps limite n'est pas d�pass�

    $maintenant = date ("U");
    if ($session->end_session < $maintenant)
      {
      // Destruction de la session
      // session_destroy();

      $requete  = "DELETE FROM Session "
                . "WHERE id_session='$session->id_session'";
      $resultat = $bd->execRequete ($requete);
      return FALSE;
    }
    else // C'est bon !
       return TRUE;
  }

  // Tentative de cr�ation d'une session

// Check that the member is an administrator
function IsAdmin ($roles) 
{
  return strstr($roles, "A");
}

// Check that the member is an administrator
function IsMember ($roles) 
{
  return strstr($roles, "M");
}


function CreerSession ($bd, $email, $motDePasse, $idSession)
{
  
  $email_safe = addSlashes ($email);
  $res = $bd->execRequete ("SELECT * FROM Personne WHERE email='$email_safe' ");
  $person = $bd->objetSuivant ($res);

  // Does the person exists?
  if ($person)
    {
      // Check the password
      if ($motDePasse == $person->password)
      {
        // Insert in Session, for 2 hours
        $maintenant = date ("U");
        $end_session = $maintenant + 7200; 
	$roles = $person->roles;

	$fname = $bd->prepareChaine($person->prenom);
	$lname = $bd->prepareChaine($person->nom);
	$insSession = "INSERT INTO Session (id_session, id_person, last_name, "
	  . "first_name, end_session, roles) VALUES ('$idSession', "
	  . "'$person->id','$lname','$fname','$end_session','$roles')";      
	$resultat = $bd->execRequete ($insSession);
	return "";
      }        
      return "<B>Ce n'est pas le bon mot de passe ! <P></B>\n";
    }      
    else
      {
	return "<B>Email inconnu</B><P>\n";
      }
  }

  function FormIdentification($nomScript, $emailDefaut="")
  {
    // Demande d'identification
    $form = new Formulaire ("POST", "$nomScript", false);
    $form->debutTable(); 
    $form->champTexte("Votre email", "email", "$emailDefaut", 30, 60);
    $form->champMotDePasse ("Votre mot de passe", "motDePasse", "", 30);
    $form->finTable();
    $form->champValider ("Log in", "ident");
    
    return $form->formulaireHTML(false);
  }

  // Fonction de contr�le d'acc�s

function CheckAccess ($nomScript, $infoLogin, $idSession, 
                         $bd, &$tpl)
{
    $sessionCourante = GetSession ($idSession, $bd);

    // Cas 1: V�rification de la session courante
    if (is_object($sessionCourante))
    {
      // La session existe. Est-elle valide ?
      if (SessionValide ($sessionCourante, $bd))
      {
	  // Reinitialize the validity period
	  $maintenant = date ("U");
	  $tempsLimite = $maintenant + 7200; 
	  $bd->execRequete ("UPDATE Session SET end_session='$tempsLimite' "
			    . "WHERE id_session = '$idSession'");

         // On renvoie l'objet session
         return $sessionCourante;
      }
      else 
	{
	  if (isSet($infoLogin['email']))
	    $email = $infoLogin['email'];
	  else
	    $email = "";
	  $tpl->set_var ("BODY", 
		   "<B>La session a expir�.<P></B>\n"
	       .     FormIdentification($nomScript, $email));
	  return null;
	}
    }
   
    // Cas 2.a: pas de session mais email et mot de passe
    if (isSet($infoLogin['email']))
      {
	if (($message = CreerSession ($bd, 
				      $infoLogin['email'], 
				      $infoLogin['motDePasse'], 
				      $idSession)) == "")
	  {
	    // On renvoie l'objet session 
	    return GetSession ($idSession, $bd);
	  }
	else 
	  {
	    $tpl->set_var ("BODY", 
			   $message
			   . "<CENTER><B>Identification failed.</B></CENTER>\n"
			   .  FormIdentification($nomScript, $infoLogin['email']));
	    return null;
	  }
      }

    // Cas 2.b : print the login form, with the default email
    if (isSet($infoLogin['email']))
      $email = $infoLogin['email'];
    else $email = "";

    $tpl->set_var ("BODY", FormIdentification($nomScript, $email), true);
    return null;
}
?>
