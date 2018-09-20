# IFTTT Smart City Data Integrator (SCDI) Project

IFTTT Smart City Data Integrator (SCDI) Project is a new medium for governments to deliver light touch digital services, that empower the citizen to customize their interaction with government while maintaining respect for their privacy. The IFTTT service has a tightly controlled user experience that allows us to present a consistent service to our citizens, in an era of poorly designed government app experiences. It enables us to democratize and operationalize data of all kinds to our citizens, solving a historic issue with Open Data, providing immediate and applicable value to the initiative. Louisville is the first government partner on this platform, which initiated the creation and adoption of Data Access Project by IFTTT.

The IFTTT Smart City Data Integrator (SCDI) Project is an _open source_ API middleware that enables citizens to customize city data sources (Open Data, IoT, Smart City data) to fit their digital needs, using the [IFTTT](https://ifttt.com) platform.

For example, Louisville Metro Government is exposing an in-house air quality API to this middleware, which connects to the IFTTT service. We have built a predefined applet that connects this to the service that Philips Hue has exposed to IFTTT. This allows the applet to trigger a color change on a Philips Hue lightbulb when the air quality changes. This is only one example, citizens can mix and match services as they like, adapting services in new and exciting ways.

**Example:**
https://ifttt.com/smartlouisville

Louisville Metro is currently exposing the following data sources to IFTTT:
- Air Quality Information  
- Rave Emergency Alerts 
- Louisville Metro News 
- Louisville Metro Events  
- Mayor Greg Fischer's podcast episodes  

**Program Features:**
The IFTTT Partner program also includes anonymized analytics, application health, performance, API testing and strict branding requirements to ensure a consistent and quality experience for citizens. Additionally it has the ability for a staging environment, private applets for testing, and suggested applets that are frequently created by users. Service implementation documentation is available [here](https://platform.ifttt.com/docs).  

**Cities & Federal Agencies that have signed up to IFTTT due to Louisville's involvement:**  
[City of Tampa](https://ifttt.com/tampa)  
[City of Edmonton, Canada](https://ifttt.com/City_of_Edmonton)  
[EPA](https://ifttt.com/epa)  
[ClinicalTrials.gov](https://ifttt.com/clinicaltrials)  
[Department of Labor](https://ifttt.com/dol)  
[Bureau of Economic Analysis](https://ifttt.com/bea)  
[SEC](https://ifttt.com/sec)  
[FCC](https://ifttt.com/fcc)  
[Library of Congress](https://ifttt.com/loc)  
[National Science Foundation](https://ifttt.com/nsf)  
[Energy Information Administration](https://ifttt.com/eia)  
[USA.gov](https://ifttt.com/usagov)  
[Department of Defense](https://ifttt.com/dod)  
[Department of Homeland Security, National Vulnerability Database](https://ifttt.com/nvd)  
[World Health Organization](https://ifttt.com/who)  
[Department of State](https://ifttt.com/dos)  

**Potential Partners:**
IFTTT  
Any cloud provider  
Federal agencies (18F, USDS, or any of the active IFTTT customers)  
Local businesses, both corporate or small/mid (for instance in Louisville we have UPS & Humana)  

**Requirements:**
[IFTTT Partner Subscription](https://platform.ifttt.com)  
PHP/MySQL (current arch, running on a T2.Micro instance on AWS.)  

**Codebase:**
https://github.com/louisvillemetro-innovation/IFTTT-Smart-City-Data-Integrator

**Open Issues / Feature Requests:**
https://github.com/louisvillemetro-innovation/IFTTT-Smart-City-Data-Integrator/issues

**Media coverage:**
https://ifttt.com/blog/2017/02/louisville-is-the-first-city-on-IFTTT  
https://www.zdnet.com/article/louisville-is-the-first-smart-city-on-the-ifttt-platform/  
http://www.govtech.com/civic/Louisville-Ky-Looks-to-IFTTT-as-Future-of-Open-Data-Among-Other-Services.html  
https://www.engadget.com/2017/02/06/louisville-ifttt-channel-smart-home-air-quality/  
https://www.cnet.com/news/hows-the-air-up-there-in-louisville-you-can-just-ask-your-light-bulbs/  
https://ifttt.com/blog/2017/06/introducing-the-data-access-project  



## Getting Started - Installation Instructions
After cloning this repo, there are a couple of preparation steps that must be taken in order to get everything set up.
1. Run the ``composer install`` at the root of the repo, to pull in all the required dependencies
2. A config file is required in order to specify your special environment variables such as API keys and special URLs.
We have provided an ```example.config.php``` file for you to start with. We recommend that you place this file somewhere outside of the project folder. Usually, you will have three of these files, ```dev.config.php```,```tst.config.php```,```prod.config.php```
which contains the correct variables for each environment.
    
    With that being said, in ```src/dependencies.php```, you will find an ```$env``` variable that is used to load the
    proper environment configuration file, as well as the location of those files. In this example, the configuration
    files are located one level up from the root folder, but you can place it anywhere you'd like and point it to the
    proper location in the ```src/dependencies.php``` file.
3.  You will need to create the tables described in the schema file that we've provided at ```schema/schema.sql```.
