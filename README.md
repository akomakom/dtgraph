Dtgraph
-----

# Requirements
1. PHP
1. Composer

# Installation

NOTE: if you are on shared hosting and can't open a shell, you run composer locally and upload the result.

These instructions are a concise summary of the general Laravel framework installation:

1. Unzip to a directory under your web root
1. Run "composer install" in the directory to get all dependencies.
   * Any errors about missing PHP extensions will need to be resolved before continuing.  Try to install these extensions via your normal package manager.
1. Alias to your web server using the method of your choosing (ie for Apache: "Alias /dtgraph /dir/of/dtgraph/public", and you may need a <Directory> section to relax your permissions, depending on your overall configuration)
1. File Permissions:
   * Writable: storage/ subdirectory ("chmod -R a+w storage" or chown to your web server user)
1. DB setup is TBD


# Configuration

*
