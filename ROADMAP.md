Cleanup Roadmap
===============

TODO:
- Make all application code PSR-12 compliant
- Setup Composer
- Move vendor files into Composer requirements
- Setup Doctrine
- Reverse-engineer Doctrine models from DDLs
- Create development docker container(s) and docker-compose files
- Add SwiftMail
- Add Twig and start separating views from controllers
- Replace config constants with environment variables
- Replace OTPHP with spomky-labs/otphp
- Replace phpqrcode with endroid/qr-code

What we need:
- A few other backend devs so we can code review everything going into the system (ideally another maintainer or two)
- Frontend devs. I (codysnider) am not one of these.
- Testers
- Folks who can write docs and tutorials
- Some organized folks to keep things moving along (sorting issues, keeping track of feature requests, etc...)


rm vendor/beberlei/ -rf
rm vendor/bin/ -rf
rm vendor/composer/ -rf
rm vendor/dasprid/ -rf
rm vendor/doctrine/ -rf
rm vendor/endroid/ -rf
rm vendor/myclabs/ -rf
rm vendor/psr/ -rf
rm vendor/spomky-labs/ -rf
rm vendor/symfony/ -rf
rm vendor/thecodingmachine/ -rf
rm vendor/twig/ -rf
