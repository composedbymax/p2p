# P2P Video Sharing App

## Overview
This application enables real-time, peer-to-peer video sharing using WebRTC. It features an automatic signaling mechanism implemented in PHP, eliminating the need for manual copy-paste of connection data.

## Features
- **WebRTC-based video streaming:** Enables live video and audio communication.
- **Automatic signaling:** Uses AJAX (Fetch API) to exchange SDP offers and answers.
- **Room management:** Create or join a room with a unique room ID.
- **Simple PHP backend:** Acts as a basic signaling server using file-based storage.

## How It Works
1. **Room Creator:**
   - Clicks "Create Room" to generate an offer.
   - The offer SDP is automatically sent via POST.
   - The creator polls for the answer and sets it once received.
2. **Joiner:**
   - Clicks "Join Room" to start polling for the offer.
   - Receives and sets the offer as the remote description.
   - Creates an answer and posts it back automatically.

```
                                                                                
                                                                                
                  .=--==--=--==--============:                          
       @@@@@@@@@@@%%%%%%#####################%@@@@@@@@@@@               
       :@@@@@@@@@@@@@@@@*#**********+++**+%@@@@@@@@@@@@@@@-              
      @@@@@@@@@@@@@@@@+**+*##########*+*%*@@@@@@@@@@@@@@@@              
      @@@@@@@@@@@@@@@@++++@@%%%+%%%@@@*##*@@@@@@@@@@@@@@@@.             
     -@@@@@@@@@@@@@@@@++++@%%#@@@@@@@@****@@@@@@@@@@@@@@@@+             
      @@@@@@@@@@@@@@@@+===@@%@@%%@@@@@****@@@@@@@@@@@@@@@@              
      @@@@@@@@@@@@@@@@++==++++++++++++++**@@@@@@@@@@@@@@@@              
      .@@@@@@@@@@@@@@@@@@#++******+++=*@@@@@@@@@@@@@@@@@@.              
       *@@@@@@@@@@#=+++++++++++++++++++++++++#@@@@@@@@@@*               
                    ==%@@@@@@@@@@@@@@@@@@%==.    +@                     
                      .@@@@@@@@@@@@@@@@@@-        @                     
                       @@@@@@@@@@@@@@@@@@         @                     
                       =@@@@@@@@@@@@@@@@=         @=                    
                        @@@@@@@@@@@@@@@@          =@                    
                        =@@@@@@@@@@@@@@=           @=                   
                         =@@@@@@@@@@@@*             @                   
                       @@@@@@@@@@@@@@@@@%            @                  
                      @@@@@@@@@@@@@@@@@@@@            @-                
                    *@@@@@@@@@@@@@@@@@@@@@@-           .                
                   @@@@@@@@@@@@@@@@@@@@@@@@@%                           
                  .@@@@@@@@@@@@@@@@@@@@@@@@@@                           
                   #@@@@@@@@@@@@@@@@@@@@@@@@+                           
              .:--=+@@@@@@@@@@@@@@@@@@@@@@@@=--:.                       
            .:-=++*##%@@@@@@@@@@@@@@@@@@@@##*++=-:.                     
                                                                                
                                                                                
                                                                                
```