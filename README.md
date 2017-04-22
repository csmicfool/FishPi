# FishPi
Modular Aquarium Controller Project for RaspberryPi and similar devices

Prerequisites:
- RaspberryPi or similar device
- Apache (or web server of choice)
- PHP (7.0 recommended)
- Wiring Pi GPIO Utility: http://wiringpi.com/the-gpio-utility/

Hardware:
- RaspberryPi3 (or whatever you have with GPIO output and runs linux more-or-less).  Cannakit is good.
- Relay Board (Use as large a board as you can based on your control board) such as this one: https://www.amazon.com/gp/product/B00KTEN3TM/ref=oh_aui_detailpage_o03_s00?ie=UTF8&psc=1
- Female-to-Femail Breadboard Jumper Wires such as these: https://www.amazon.com/gp/product/B01EV70C78/ref=oh_aui_detailpage_o03_s00?ie=UTF8&psc=1
- Project Enclosure such as this one: https://www.amazon.com/gp/product/B00O9YY1G2/ref=oh_aui_detailpage_o01_s00?ie=UTF8&psc=1
- Various electrical components such as wire, outlets, boxes, etc.  PLEASE DO NOT WIRE 120/220 Volt circuits if you are not knowledgeable and comfortable with the safety requirements.  YOU CAN BURN YOUR HOUSE DOWN.  PLEASE DONT BURN YOUR HOUSE DOWN.

Wiring:
- [FILL IN GPIO INFO HERE LATER]
- Intentionally not providing wiring guidance for the AC circuits as this can be applied in many ways and should only be done by those who are competent.  See earlier warnings about amateur electrical work.  YOU CAN BURN YOUR HOUSE DOWN.  PLEASE DONT BURN YOUR HOUSE DOWN.  THIS IS AT YOUR OWN RISK

Installation:
- See prerequisites above
- Copy to web directory
- Configure the config.json file according to your tank and accessory specs, location, etc.  Use the provided file as an example.
- Ensure that config.json and state.json are both writeable by your web server.

Please post issues in Github