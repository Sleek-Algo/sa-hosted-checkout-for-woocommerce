import React from 'react';
import { Card, Badge, Button, Divider, Space } from 'antd';
import './../styles/promotion.scss';

const { Meta } = Card;
const Promotion = () => {
	return (
		<div className="sahcfwc-app-promotion-main">
			<div className="sahcfwc-app-promotion-header">
				<h1>Promotion</h1>
			</div>
			<Divider className="sahcfwc-app-promotion-divider" />
			<div className="sahcfwc-app-promotion-body">
				<Space
					direction="vertical"
					size="middle"
					style={ {
						width: '100%',
					} }
				>
					<Badge.Ribbon
						text={
							<span
								style={ {
									color: '#fff',
									display: 'block',
									margin: '3px 0',
									fontWeight: '600',
								} }
							>
								Limited Time Offer: 50% Off
							</span>
						}
						color="magenta"
						style={ {
							marginBottom: '22px',
						} }
					>
						<Card
							hoverable
							cover={
								<img
									alt="example"
									src="https://www.flycart.org/wp-content/uploads/2022/07/how-do-i-add-a-discount-in-woocommerce.jpeg"
									height={ '200px' }
									style={ {
										objectFit: 'cover',
										objectPosition: 'top',
									} }
								/>
							}
						>
							<Meta
								title="Wocoommerce Stripe Checkout Plugin"
								description={ <Button>Order Now</Button> }
							/>
						</Card>
					</Badge.Ribbon>
					<Badge.Ribbon
						text={
							<span
								style={ {
									color: '#fff',
									display: 'block',
									margin: '3px 0',
									fontWeight: '600',
								} }
							>
								Exclusive Deal: 30% Off
							</span>
						}
						color="purple"
					>
						<Card
							hoverable
							cover={
								<img
									alt="example"
									src="https://i.ytimg.com/vi/ZBnfFqtB_jc/hq720.jpg?sqp=-oaymwEhCK4FEIIDSFryq4qpAxMIARUAAAAAGAElAADIQj0AgKJD&rs=AOn4CLA6YE6G4BG3yLuHbn6oGXkz4B7cMQ"
									height={ '200px' }
									style={ {
										objectFit: 'cover',
										objectPosition: 'top',
									} }
								/>
							}
						>
							<Meta
								title="Wocoommerce Custom Order"
								description={ <Button>Order Now</Button> }
							/>
						</Card>
					</Badge.Ribbon>
				</Space>
			</div>
		</div>
	);
};

export default Promotion;
