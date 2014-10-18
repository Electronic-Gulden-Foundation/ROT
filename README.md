ROT 
===

Ring of Trust

One major cryptocoin bottleneck is the dependancy of a strong, large network of clients, collaborating to distribute the blockchain and single transactions. To download the bitcoin blockchain it currently takes about three days and consumes 34 GB (october 2014). When you open a Bitcoin blockchain-server, the network bandwith approaches 400 GB a month. The need for light-weight client-solutions increases but this endangers the distributed nature of Bitcoin. 

With the mining component we already saw clustering of computingpower into Pools. Solo-mining is not feasable. It remains important to avoid 51% dominance of one mining pool for security reasons, but mining contributors are advised to avoid adhering to the largest pool.

Once the blochchain load will become too heavy for a normal enduser, endusers are tempted to have their wallets hosted. The alternative is to use centralised blockchain servers such as Mycelium and blochchain.info to keep the keys private but these are proprietary solutions. What would be necessary is to have distributed blockchain-services that are thrustworthy and that can by used by light clients to perform the necessary tasks to complete transactions.

The lightweight client must trust the blockchain-server, for example to show the correct balances and to allow the client to select and spend inputs that are his. To avoid single points of failure and to stimulate a large network of blockchain-services, the first contributors to think of are the mining-pools and exchanges that will allways need the entire blockchain and that could provide transaction services to light clients. A second option is the elaboration of a "Ring Of Trust", consisting of a network of collaborating blockchain servers and a small transaction fee to be agreed upon, based on the operational costs of these services. Ideally, the client savings to get rid of the weight of the blockchain, should outweigh the cost of the maintenance of a ring of thrust and therefor a realistic distribution of these costs is necessary.

The functions to be provided are :
- Functions to prove the internal blockchain integrity within the Ring of Trust.
- Functions to synchronize the client database with known inputs
- The client must be able to select multiple server nodes (or passively receive multiple connections/peers) 
- ....

To elaborate a ring of thrust we start by setting up a block-chain service. It currently consists of an index-builder and a small http-interface. The index consists of two parts :
- An index of all transactions to allow browsing through the blockchain
- An index of all public keys; Each public key consists of a file with all inputs and outputs

The next challenge will be to interconnect these services into a ring, chain, tree or otherwise. These services will need to test each other to enhance trust and to provide a comfortable level of redundancy.
