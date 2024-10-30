import React from 'react';
import NavTabs from './NavTabs';
import { Col, Row, Card } from 'antd';

const Content = () => {
	return (
		<>
			<div className="sahcfwc-app-main-container">
				<Row justify="space-between">
					<Col xs={ 24 } sm={ 24 } md={ 24 } lg={ 24 } xl={ 24 }>
						<div className="sahcfwc-app-content-wrapper">
							<NavTabs />
						</div>
					</Col>
				</Row>
			</div>
		</>
	);
};

export default Content;
