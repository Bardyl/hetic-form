<?php
/*
 Plugin Name: HETIC FORM
 Version: 0.1
 Plugin URI: hetic.net
 Description: Display a form
 Author: It's me! Mario !
 Author URI: http://www.hetic.fr

 TODO:
	* Etape 1 : Création du plugin
	* Etape 2 : Init du shortcode
	* Etape 3 : Affichage du formulaire
	* Etape 4 : Traitement PHP du formulaire ( champs requis etc. )
	* Etape 5 : Insertion du contenu
	* Etape 6 : Sécurité
	* Etape 7 : Images
 */

// URL vers l'url du plugin et son chemin absolu
define('HETIC_FORM_URL', plugin_dir_url ( __FILE__ ));
define('HETIC_FORM_DIR', plugin_dir_path( __FILE__ ));

add_action( 'plugins_loaded', 'hetic_form_init' );

function hetic_form_init() {
	// On ajoute le shortcode
	add_shortcode( 'hetic-form', 'hetic_form_shortcode' );

	// On se place au moment du template_redirect car aucune information n'est encore affichée
	add_action( 'template_redirect', 'hetic_form_process_form' );

	// Ajout des fiels acf
	add_action(  'init' , 'hetic_form_fields' );
}

function hetic_form_shortcode() {
	// init du global des messages pour les remplir au fur et à mesure
	global $hetic_form_messages;

	// on commence un buffer
	ob_start();

	// on affiche le fichier hetic-form.php
	include_once( HETIC_FORM_DIR.'/vues/hetic-form.php' );

	// On récupère le texte et on le retourne !
	return ob_get_clean();
}

function hetic_form_process_form() {
	global $hetic_form_messages;

	// Si on a pas soumis le formulaire alors on ne commence pas à traiter les informations
	if( !isset( $_POST['hetic_form_submit'] ) || !isset( $_POST['_wpnonce'] ) ) {
		return false;
	}

	// On vérifie le nonce pour être sûr que c'est bien ajouté
	if( !wp_verify_nonce( $_POST['_wpnonce'], 'hetic_form_submit' ) ) {
		$hetic_form_messages .= 'Erreur de sécurité, veuillez retenter d\'envoyer le formulaire.<br/>';
		return false;
	}

	if( !isset( $_POST['hetic_form_title'] ) || empty( $_POST['hetic_form_title'] ) ) {
		$hetic_form_messages .= 'Vous devez remplir un titre<br/>';
	}

	if( !isset( $_POST['hetic_form_content'] ) || empty( $_POST['hetic_form_content'] ) ) {
		$hetic_form_messages .= 'Vous devez remplir un contenu<br/>';
	}

	if( !isset( $_POST['hetic_form_name'] ) || empty( $_POST['hetic_form_name'] ) ) {
		$hetic_form_messages .= 'Vous devez remplir votre nom<br/>';
	}

	if( !isset( $_POST['hetic_form_firstname'] ) || empty( $_POST['hetic_form_firstname'] ) ) {
		$hetic_form_messages .= 'Vous devez remplir votre prénom<br/>';
	}

	// on vérifie que la catégorie a été choisie
	if( !isset( $_POST['hetic_form_category'] ) || empty( $_POST['hetic_form_category'] ) ) {
		$hetic_form_messages .= 'Vous devez choisir un type de message<br/>';
	} else {
		if( !term_exists( absint( $_POST['hetic_form_category'] ), 'category' ) ) {
			$hetic_form_messages .= 'Vous devez choisir un type de message valide<br/>';
		}
	}

	// On tag en disant que l'on ne veut pas upload de fichier
	$upload_file = false;
	 // On vérifie que l'image soit présente
    if( isset( $_FILES['image'] ) && $_FILES['image']['error'] == 0 ) {

    	// On tente de récupérer les dimensions de l'image puisque l'on attend d'avoir une image.
    	if( ( $size = getimagesize( $_FILES['image']['tmp_name'] ) ) === false ) {
			$hetic_form_messages .= 'Vous devez fournir une image<br/>';
		} else {
			$upload_file = true;
		}
    }

	// S'il y a des messages à afficher, alors on ne continue pas.
	if( !empty( $hetic_form_messages ) ) {
		return false;
	}

	// on insère les données
	$inserted = wp_insert_post( array(
		'post_title' => sanitize_text_field( $_POST['hetic_form_title'] ), // On protège le titre
		'post_content' => wp_kses( $_POST['hetic_form_content'], array() ), // Aucun tag HTML autorisé
		'post_type' => 'post',
		'post_status' => 'pending'
	) );

	// On vérifie que WordPress a réussit
	if( is_wp_error( $inserted ) ) {
		$hetic_form_messages = 'Impossible d\'enregistrer votre demande !';
		return false;
	}

	// On affiche un message de succès di possible
	$hetic_form_messages = 'Votre demande a été correctement enregistrée';

	// On insère un champs personnalisé, ici l'ip de la personne, nom et prénom
	update_post_meta( $inserted, 'ip', $_SERVER['REMOTE_ADDR'] );
	update_post_meta( $inserted, 'name', sanitize_text_field( $_POST['hetic_form_name'] ) ); // On protège le contenu
	update_post_meta( $inserted, 'first_name', sanitize_text_field( $_POST['hetic_form_firstname'] ) ); // On protège le contenu

	// On ajoute le terme au post, on vérifie que l'on ait un id
	wp_set_object_terms( $inserted, absint( $_POST['hetic_form_category'] ), 'category' );

	// On ajoute les librairies de WP qui correpondent à l'upload de fichier
	include( ABSPATH.'/wp-admin/includes/file.php' );
	include( ABSPATH.'/wp-admin/includes/image.php' );
	include( ABSPATH.'/wp-admin/includes/media.php' );

    // On télécharge l'image et on l'associe tout de suite à l'article inséré plus tôt
    $thumb_id = media_handle_upload( "image", $inserted  );
    if ( is_wp_error( $thumb_id ) || (int) $thumb_id <= 0 ) {
        return false;
    }
    set_post_thumbnail( $inserted , $thumb_id);

	return true;
}

function hetic_form_fields() {
	if( function_exists( "register_field_group" ) ) {
		register_field_group( array (
			'id' => 'acf_champs-inseres',
			'title' => 'Champs inseres',
			'fields' => array (
				array (
					'key' => 'field_525ee70b2bc5e',
					'label' => 'IP',
					'name' => 'ip',
					'type' => 'text',
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'formatting' => 'html',
					'maxlength' => '',
				),
				array (
					'key' => 'field_525ee7192bc5f',
					'label' => 'Nom',
					'name' => 'name',
					'type' => 'text',
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'formatting' => 'html',
					'maxlength' => '',
				),
				array (
					'key' => 'field_525ee7202bc60',
					'label' => 'Prénom',
					'name' => 'first_name',
					'type' => 'text',
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'formatting' => 'html',
					'maxlength' => '',
				),
			),
			'location' => array (
				array (
					array (
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'post',
						'order_no' => 0,
						'group_no' => 0,
					),
				),
			),
			'options' => array (
				'position' => 'normal',
				'layout' => 'default',
				'hide_on_screen' => array (
				),
			),
			'menu_order' => 0,
		) );
	}
}