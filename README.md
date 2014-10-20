ROT 
===

Ring of Trust

A major cryptocoin bottleneck is the dependency on a powerful and, by necessity, large network of clients, which all collaborate to distribute both the blockchain and individual transactions. To download the full Bitcoin blockchain into the client software may take up to three days and already requires over 34 GBytes of available diskspace (October 2014). When you run a Bitcoin blockchain server, the network bandwidth required rapidly approaches 400 GBytes a month. The need for light-weight client solutions is apparent, but this same need may endanger the distributed nature of Bitcoin. 

With the mining component of Bitcoin we have already seen a clustering of computing power into mining pools. Solo mining is simply no longer feasible. For security reasons it is important to avoid the 51% dominance of one mining pool over the other miners, and mining contributors are therefore advised to avoid to contribute solely to the largest pool.

Once the blockchain load becomes too heavy or too unmanageable for a typical enduser, people may look at having their wallets hosted online. An alternative is to use centralised blockchain servers such as Mycelium or blockchain.info to keep keys private, but these are proprietary solutions. What is really needed is the availability of trustworthy distributed blockchain services, which can by used by lightweight clients to perform the tasks necessary to complete transactions.

Such a lightweight client must trust the centralised blockchain-server, in order to show the correct balances and to allow the user of the client to select and spend inputs that are his own. 

To avoid having propietary possible single points of failure and to stimulate the emergence of a large network of blockchain services, the first possible contributors to think about are the mining pools and exchanges.  They will allways need to load the whole blockchain anyway and they could therefore provide transaction services to lightweight clients. Another option is the establishment of a "Ring of Trust", which should be made up of a network of collaborating blockchain servers. Their operating costs are to be covered by a small transaction fee, the height of which needs to be agreed upon. 

Ideally the savings attained by users, by getting rid of the weight of the blockchain, outweighs the maintenance costs of a Ring of Trust and a realistic and fair distribution of such costs is thus a definite requirement.

The services to be provided are :

- Functions to proof the internal blockchain integrity within the Ring of Trust.
- Functions to synchronize the client database with known inputs 
- The client software must be enabled to select multiple server nodes (or passively receive multiple connections/peers)  
- ....

To build a Ring of Trust we will start by setting up a blockchain service. This service will incorporate an index builder and a simple http interface. The index consists of two parts :
- An index of all transactions to allow browsing through the blockchain 
- An index of all public keys; each public key is a file containing all inputs and outputs 

The next step is to interconnect these services into a ring, chain, tree or such. These services will need to continuously test each other to both enhance trust and to provide a comfortable level of redundancy.
