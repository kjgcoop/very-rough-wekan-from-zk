# Intro - This is a VERY Rough Draft and Will Not Be Updated
This is a VERY rough script to import Zenkit boards into Wekan using .json files exported from Zenkit. I performed some minor cleanup since I last ran it, but I've already spun down my Wekan server, so I haven't run it. It's possible I broke everything ever and we'll all die. 

I'm no longer using Wekan, so I don't plan to update this. However, do feel free to fork it for your own devious purposes. I'm releasing it into the world in hopes that someone will find it useful.

It's been many months since I ran this, so my recollection is foggy. However, this is what I've put back together between my brain and the code.

# Background
One of the really interesting this about Zenkit is that it has batches of tags and any batch can be used as column headings. Consequently, when you export a board, the cards aren't already hierarchically organized into lists. The script defaults to using the first batch of tags Zenkit made as list headers. That tag batch is called Stage. Cramming cards into lists was a royal pain in the butt. That's the only reason I'm sharing this - to spare any other (former?) Zenkit users that butt pain.  

If you don't want Stage to be its list headings, change the value in WekanBoard->defaultStageTitle. If the defaultStageTitle isn't found on a given card, it'll let you know there's something weird going on and die. 

# To Use
## First Use
1. Copy .example_config to whatever environment(s) you plan to run this on. When I was using this, I had a .dev and a .production. Change the $env value in import.php to match the name of the config file you want to use. You may be saying to yourself, "Wouldn't it make more sense to have that as a command line option?" Yes it would. See the above caveat about this being very rough.
2. Procure a user ID and token from Zenkit and put them in your config file. 
   - This could be scripted, but it doesn't expire, so it saved me dev time to get the data in Postman then hard-code the values into the config file. 
   - There are functions in import.php getUserToUse() and login() but they're not used anywhere. I don't even remember if they work.

## Each execution
1. Confirm import.php's $env file is pointing to the environment you want to use.
2. Confirm the directory you want to read from is the same one that's in your config file.
3. You may want to change WekanBoard->defaultStageTitle. See the Background heading for details.
4. Export a JSON file from Zenkit
5. Change the value of ZK_JSON_DOR in your config file to the absolute path of the directory containing the JSON exports
6. `php import.php`
7. Celebrate

# Limitations
- Attachments? What are those?
   - There's a URL to attachments in [big hash]_filesData. For example: https://projects.zenkit.com/api/v1/lists/2342635/files/4609289 has "id": "4609289, and "listId": 2342635
      - Do all files follow that format?
- The API call that imports tags hangs. 
- Doesn't detect dupicate boards - if you import the same file twice, enjoy your duplicate data.
- Wekan supports more than one swimlane, but this script does not.
- Lists are in no particular order
- Get the boards from ZK directly; I think it returns the same json the manual export returns, so it should be pretty simple. However, see https://www.globalnerdy.com/2021/06/07/the-programmers-credo/
- WekanBoard->create() hard-codes the board's body property with test data. It probably isn't to your liking.
- Logging would be cool.
- I didn't test for what would happen if you have two cards with the same name in the same list. Maybe bad things. 
- In Zenkit you can choose whether you can apply multiple tags from the same batch to a card. If there's more than one stage attached to the card, this script will alert you that it needs manual intervention. It will not save the weird data.
- Zenkit has a bunch of properties not supported by Wekan's creation API :(
- Wekan's add API has a bunch of properties not in use with incoming ZK data - ignore them.
- I didn't see a way to import tag color into Wekan.
- There is a WekanComment class, but I don't remember ever putting it to use.
- Wekan's checklist item creation API is incomplete, therefore this script's ability to import checklists is incomplete. I don't remember the exact problem, just that there was one. I want to say there's no way to add list items.

# Legalese
Copyright @ 2023 KJ Coop licensed under [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.txt). I'm not responsible that any undesireable consequences of running this script.
