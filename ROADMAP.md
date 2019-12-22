Cleanup Roadmap
===============

DONE:
- ~Setup Composer~
- ~Move CLI scripts to bin/~
- ~Add Roadrunner~
- ~Add Twig~ and start separating views from controllers
- ~Replace config constants with environment variables~
- ~Setup Doctrine~
- ~Reverse-engineer Doctrine models from DDLs~
- ~Create development docker container(s) and docker-compose files~

TODO:
- Make all application code PSR-12 compliant
- Move vendor files into Composer requirements
- Add SwiftMail
- Replace OTPHP with spomky-labs/otphp
- Replace phpqrcode with endroid/qr-code
- Migrate translations to YAML format
- Migrate view and functionality from index.php and rename bootstrap.php to index.php
- Convert login for to Bootstrap 4
- Configure fontawesome
- Better setup documentation re: what environment variables need to be changed
- Bash/Powershell scripts for updating to latest from master
- Switch password mechanism to bcrypt

What we need:
- A few other backend devs so we can code review everything going into the system (ideally another maintainer or two)
- Frontend devs. I (codysnider) am not one of these.
- Testers
- Folks who can write docs and tutorials
- Some organized folks to keep things moving along (sorting issues, keeping track of feature requests, etc...)
