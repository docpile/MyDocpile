<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) { 
	http_response_code(404);
	exit();
}


// =========================================================================================
// PASSWORD MANAGEMENT
// =========================================================================================
// Add more users as needed in the format: 'username' => 'hashed_password'
// Default admin password before installation (will be changed during install): ThiSiSaSecrEtPassWorD1_2,3
$users = [
    'cloudadmin' => '$argon2id$v=19$m=65536,t=4,p=1$LlQtv0xpKv+vC2Ts1o5WFA$Xws+keYruDrwZ012TE1LuIRkrADMFejo7G1U1FwMnfE',
];

// Details of the user account, as well as its rights
// Syntax:
// $user_details = [
// 	[	'name'  	=> 'Username', 
//		'cloud' => [  					<- Clouds array
//			'default' => [  		    <- Cloud name
//					'path' => '/path/to/the/data',
//					'rights' 	=> 'modify',    <- Specific cloud rights.    Possible values: See $MYCLOUD_RIGHTS_MATRIX
//                  'interface' => 'gallery',   <- Optional. Can be default, email, gallery, symbol or symbol-dark 
//			],
///			'cloud2' => [  		    <- Cloud name
//					'path' => '/path/to/the/data2',
//					'rights' 	=> 'modify',    <- Specific cloud rights.    Possible values: See $MYCLOUD_RIGHTS_MATRIX
//		],
//	],

//    [
//        'name' => 'demouser',
//        'role' => 'cloud',
//        'cloud_webdav' => true,
//        'cloud' => [
//            'My Cloud' => [
//                'path' => '/home/mycloud/cloudadmin',
//                'rights' => 'full',
//            ],
//            'test' => [
//                'path' => '/home/mycloud/test',
//                'rights' => 'full',
//            ],
//            'Server 1' => [
//                'path' => 'myuser@10.2.1.1:3222',
//                'rights' => 'admin_mode',
//            ],
//        ],
//    ],


// =========================================================================================
// USER MANAGEMENT
// =========================================================================================
$user_details = [
    [
        'name' => 'cloudadmin',
        'role' => 'admin',
        'cloud_webdav' => true,
        'cloud' => [
            'My Cloud' => [
                'path' => '/home/mydocpile/cloudadmin',
                'rights' => 'full',
            ],
            'My Mails' => [
                'path' => '/home/mydocpile/dummy',
                'rights' => 'mail-full',
                'interface' => 'email',
            ],
        ],
    ],

];
// =========================================================================================


// ==========================================
// MYCLOUD RIGHTS MATRIX
// ==========================================
// 
// Core File Operations
//    treeview_button (Show or hide the "Tree view" toolbar button)
//    iconview_button (Show or hide the "Icon view" toolbar button)
//    newfile (Create new text files)
//    newfolder (Create new directories)
//    upload (Upload files via drag-and-drop or dialog)
//    download (Download files or batch downloads)
//    view_commander (Commander view)
//    view_office (Office view)
//    preview (Open the internal image/video/text viewer)
//    edit_file (Open the Ace Editor or ONLYOFFICE)
//    print (Send documents to the browser's print dialog)
//    copy (Copy items)
//    move (Move items)
//    duplicate (Duplicate items in place)
//    rename (Rename items)
//    delete (Send items to the recycle bin or permanently delete)
//    copy_as (Create a timestamped copy of template files)
//    overwrite (Overwriting files or folders)
//    search (Search UI)
// 
// Archive Management
//    zip_copy (Create a ZIP archive from selected items)
//    unzip (Extract contents of a ZIP archive)
//
// PDF Toolkit
//    pdf_stack_menu / pdf_stack (Merge multiple documents into a single PDF)
//    pdf_unstack (Split a PDF into individual pages)
//    pdf_combine_images (Convert and merge images into a PDF)
//    pdf_toolkit / pdf_tools (Access the advanced PDF manipulation sidecar)
// 
// Recycle Bin
//    restore (Restore items to their original location)
//    restore_to (Restore items to a custom selected location)
//    empty_bin (Permanently delete all items in the recycle bin)
// 
// Security
//    encrypt (Create or edit an encrypted vault)
//    change_vault_pwd (Chanve the password of a vault)
// 
// Sharing & Collaboration
//    share (Create or edit a share link for a specific item)
//    share_all / share-list (Open the global Share Manager dialog)
// 
// Webmail Module
//    email_send (Sending mails)
//    email_contacts (Contacts dialog)
//    email_settings (Change mail settings dialog restriction)
//    email_add_foreign_servers (Change mail settings dialog foreign servers restriction)
//    email_import_contacts (Import or export contacts)
//    email_delete (Deleting mails)
//    email_newfolder (New mail folders)
//    email_rename (Rename folders)
//    email_copy (Copying mails)
//    email_move (Moving mails)
// 
// Metadata & System
//    selection_buttons (Show or hide the toolbar selection buttons)
//    properties (View file/folder size stats and treemaps)
//    permissions (View or modify owner/group access rights)
//    edit_tags (Color tags in the views)
//    fav_toggle (Add or remove items from the Favorites sidebar)
//    settings (Settings UI)
//    help (Help UI)

// You can also nest rights one level.

$MYCLOUD_RIGHTS_MATRIX = [
    'full' => [
        'blocked' => [] // Allowed everything
    ],
    'modify' => [
        'blocked' => ['permissions', 'properties', 'share', 'share_all'] 
    ],
    'edit-print' => [
        'blocked' => [
			'modify', // Inherits 'modify' blocks
			'pdf_unstack', 'pdf_stack', 'pdf_stack_menu', 'newfile', 'copy', 'download', 
			'pdf_tools', 'pdf_toolkit', 'pdf_combine_images', 'preview', 'edit_tags', 'selection_buttons', 
			'fav_toggle', 'zip_copy', 'unzip', 'treeview_button', 'iconview_button', 'ai_copilot', 
			'restore_to', 'view_commander', 'search', 'encrypt', 'change_vault_pwd'
        ]
    ],
    'edit-only' => [
        'blocked' => [
			'edit-print', // Inherits 'edit-print' and 'modify' blocks
			'restore', 'empty_bin', 'delete', 'move', 'upload', 'print', 'settings', 'help'
        ]
    ],
    'read-only' => [
        'blocked' => [
			'modify', // Inherits 'modify' blocks
			'newfolder', 'newfile', 'copy', 'move', 'duplicate', 'rename', 'delete', 'upload', 'view_commander', 
			'edit_file', 'pdf_stack', 'pdf_stack_menu', 'pdf_unstack', 'pdf_tools', 'edit_tags', 'selection_buttons', 
			'pdf_toolkit', 'pdf_combine_images', 'restore', 'empty_bin', 'zip_copy', 'iconview_button', 'ai_copilot', 
			'unzip', 'copy_as', 'view_office', 'encrypt', 'change_vault_pwd'
        ]
    ],
    'mail-read-only' => [
        'blocked' => [
			'read-only', 'edit-only', 'email_send', 'email_contacts', 'email_import_contacts', 'email_settings', 
			'email_add_foreign_servers', 'email_delete', 'email_copy', 'email_move', 'email_newfolder', 'email_rename', 'help'
		] 
    ],
    'mail-reduced' => [
        'blocked' => ['read-only', 'email_import_contacts', 'email_settings', 'email_add_foreign_servers',  ] 
    ],
    'mail-default' => [
        'blocked' => ['read-only', 'email_add_foreign_servers' ] 
    ],
    'mail-full' => [
        'blocked' => ['read-only' ] 
    ],
    'no-access' => [
        'blocked' => '*' // Wildcard blocks everything
    ],
];